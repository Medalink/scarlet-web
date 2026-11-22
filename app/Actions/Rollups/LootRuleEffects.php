<?php

namespace App\Actions\Rollups;

use App\Support\LootArm;
use App\Support\LootGroupScope;
use App\Support\LootRule;
use App\Support\LootSourceRef;
use App\Support\RateMath;
use App\Support\RateSample;
use App\Support\Rollup;
use App\Support\RollupRead;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

class LootRuleEffects
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * What a corpse held under two loot rules (docs/LOOT.md, Loot rules and group size): the items per corpse under
     * each, and their difference, over the sources seen under both rules with `RateSample::HOLLOW_FROM` corpses or more
     * in each, pooled with Mantel-Haenszel weights. The figure over all sources is never made: a rule is played on
     * other creatures than another. `$first` and `$second` choose the rules; without them it is the two with the most
     * corpses on sources seen under two rules. A free-for-all arm of two or more characters counts its one reader
     * (`LootArm`). `more` counts the sources seen under both rules with fewer corpses in one of them.
     *
     * @return array{seen: list<array{rule: string, windows: int, sources: int}>, pair: list<string>, rows: list<array{rule: string, items: float, corpses: int}>, sources: int, windows: int, difference: array{difference: float, low: float, high: float}|null, more: int}
     */
    public function handle(?LootSourceRef $source, ?LootRule $first, ?LootRule $second): array
    {
        $seen = $this->seen($source);
        $pair = $this->pair($seen, $first, $second);
        $answer = ['seen' => $seen, 'pair' => $pair, 'rows' => [], 'sources' => 0, 'windows' => 0, 'difference' => null, 'more' => 0];
        if (count($pair) < 2) {
            return $answer;
        }

        [$pooled, $corpses, $more] = $this->pool($this->arms($source, $pair), $pair);
        if ($pooled === null) {
            return ['more' => $more] + $answer;
        }

        return [
            'seen' => $seen, 'pair' => $pair,
            'rows' => [
                ['rule' => $pair[0], 'items' => $pooled['first'], 'corpses' => $corpses[0]],
                ['rule' => $pair[1], 'items' => $pooled['second'], 'corpses' => $corpses[1]],
            ],
            'sources' => $pooled['sources'], 'windows' => $pooled['windows'],
            'difference' => ['difference' => $pooled['difference'], 'low' => $pooled['low'], 'high' => $pooled['high']], 'more' => $more,
        ];
    }

    private function perSource(?LootSourceRef $source): Builder
    {
        return LootGroupScope::of(DB::table(Rollup::LootWindowsCharacterDaily->value), $source)
            ->whereIn('loot_rule', LootGroupScope::knownRules())
            ->selectRaw('source_kind, source_id, loot_rule, sum(windows) as windows')->groupBy('source_kind', 'source_id', 'loot_rule');
    }

    /**
     * The rules over sources seen under two rules or more, the one with most corpses first.
     *
     * @return list<array{rule: string, windows: int, sources: int}>
     */
    private function seen(?LootSourceRef $source): array
    {
        $per = $this->perSource($source);
        $several = DB::query()->fromSub($per, 's')->select('s.source_kind', 's.source_id')->groupBy('s.source_kind', 's.source_id')->havingRaw('count(*) >= 2');
        $rows = $this->read->rows(DB::query()->fromSub($per, 'p')
            ->joinSub($several, 'm', fn (JoinClause $join) => $join->on('m.source_kind', '=', 'p.source_kind')->on('m.source_id', '=', 'p.source_id'))
            ->selectRaw('p.loot_rule, sum(p.windows) as windows, count(*) as sources')->groupBy('p.loot_rule')
            ->orderByRaw('sum(p.windows) desc')->orderBy('p.loot_rule'));

        return array_map(fn (array $row): array => ['rule' => (string) $row['loot_rule'], 'windows' => (int) $row['windows'], 'sources' => (int) $row['sources']], $rows);
    }

    /**
     * @param  list<array{rule: string, windows: int, sources: int}>  $seen
     * @return list<string>
     */
    private function pair(array $seen, ?LootRule $first, ?LootRule $second): array
    {
        $chosen = array_values(array_unique(array_filter([$first?->value, $second?->value])));
        $rest = array_values(array_filter(array_column($seen, 'rule'), fn (string $rule): bool => ! in_array($rule, $chosen, true)));
        $pair = array_slice([...$chosen, ...$rest], 0, 2);

        return count($pair) === 2 ? $pair : [];
    }

    /**
     * @param  list<string>  $pair
     * @return array<string, array<string, array<int, array{windows: int, items: int, squares: int}>>> by source, rule and character
     */
    private function arms(?LootSourceRef $source, array $pair): array
    {
        $both = LootGroupScope::of(DB::table(Rollup::LootWindowsCharacterDaily->value), $source)
            ->whereIn('loot_rule', $pair)->select('source_kind', 'source_id')->groupBy('source_kind', 'source_id')->havingRaw('count(distinct loot_rule) = 2');
        $rows = $this->read->rows(LootGroupScope::of(DB::table(Rollup::LootWindowsCharacterDaily->value.' as t'), $source, 't.')
            ->whereIn('t.loot_rule', $pair)
            ->joinSub($both, 'b', fn (JoinClause $join) => $join->on('b.source_kind', '=', 't.source_kind')->on('b.source_id', '=', 't.source_id'))
            ->selectRaw('t.source_kind, t.source_id, t.loot_rule, t.character_id, sum(t.windows) as windows, sum(t.item_rows) as items, sum(t.item_rows_squared) as squares')
            ->groupBy('t.source_kind', 't.source_id', 't.loot_rule', 't.character_id'));
        $by = [];
        foreach ($rows as $row) {
            $by[$row['source_kind'].$row['source_id']][(string) $row['loot_rule']][(int) $row['character_id']] = ['windows' => (int) $row['windows'], 'items' => (int) $row['items'], 'squares' => (int) $row['squares']];
        }

        return $by;
    }

    /**
     * @param  array<string, array<string, array<int, array{windows: int, items: int, squares: int}>>>  $sources
     * @param  list<string>  $pair
     * @return array{0: array{difference: float, low: float, high: float, first: float, second: float, sources: int, windows: int}|null, 1: list<int>, 2: int}
     */
    private function pool(array $sources, array $pair): array
    {
        $pooled = [];
        $corpses = [0, 0];
        $more = 0;
        foreach ($sources as $rules) {
            $armed = array_map(fn (string $rule): ?array => $this->arm($rule, $rules[$rule] ?? []), $pair);
            if ($armed[0] === null || $armed[1] === null) {
                continue;
            }
            if ($armed[0]['n'] < RateSample::HOLLOW_FROM || $armed[1]['n'] < RateSample::HOLLOW_FROM) {
                $more++;

                continue;
            }
            $pooled[] = ['n1' => $armed[0]['n'], 'sum1' => $armed[0]['sum'], 'squares1' => $armed[0]['squares'], 'n2' => $armed[1]['n'], 'sum2' => $armed[1]['sum'], 'squares2' => $armed[1]['squares']];
            $corpses[0] += $armed[0]['n'];
            $corpses[1] += $armed[1]['n'];
        }

        return [RateMath::pooledDifference($pooled), $corpses, $more];
    }

    /**
     * The windows, item rows and their squares of one rule's arm on one source: every character's, or its one reader's.
     *
     * @param  array<int, array{windows: int, items: int, squares: int}>  $byCharacter
     * @return array{n: int, sum: int, squares: int}|null
     */
    private function arm(string $rule, array $byCharacter): ?array
    {
        $arm = LootArm::of($rule, LootRule::words($rule), array_map(fn (array $row): int => $row['windows'], $byCharacter), $rule === LootRule::FreeForAll->value, false, false, (int) config('scarlet.min_characters'));
        if ($arm === null) {
            return null;
        }
        $counted = array_intersect_key($byCharacter, array_flip($arm->characters));

        return ['n' => $arm->windows, 'sum' => array_sum(array_column($counted, 'items')), 'squares' => array_sum(array_column($counted, 'squares'))];
    }
}
