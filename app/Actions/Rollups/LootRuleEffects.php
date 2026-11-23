<?php

namespace App\Actions\Rollups;

use App\Support\CountedLoot;
use App\Support\LootArm;
use App\Support\LootCounted;
use App\Support\LootDay;
use App\Support\LootGroupScope;
use App\Support\LootRule;
use App\Support\LootSourceKind;
use App\Support\LootSourceRef;
use App\Support\RateMath;
use App\Support\RateSample;
use App\Support\RollupRead;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

class LootRuleEffects
{
    public function __construct(private readonly RollupRead $read, private readonly CountedLoot $counted, private readonly MidnightWindows $midnights) {}

    /**
     * What a corpse held under two loot rules (docs/LOOT.md, Loot rules and group size): the items per corpse under
     * each, and their difference, over the sources seen under both rules with `RateSample::HOLLOW_FROM` corpses or more
     * in each, pooled with Mantel-Haenszel weights. The figure over all sources is never made: a rule is played on
     * other creatures than another. `$first` and `$second` choose the rules; without them it is the two with the most
     * corpses on sources seen under two rules. A corpse with a key counts once (docs/LOOT.md, Units of counting, rule 7); the windows with no key of a free-for-all
     * arm of two or more characters count their one reader, and the larger of two sets only on the days where both exist (`LootArm::overDays`). `more` counts the sources seen under both rules with fewer corpses in one of them.
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

        [$pooled, $corpses, $more] = $this->pool($this->arms($source, $pair), $pair, $this->midnights->handle($source === null ? null : [$source]));
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
        return LootGroupScope::of(DB::table(LootCounted::WindowsCharacter->value), $source)
            ->whereIn('loot_rule', LootGroupScope::knownRules())
            ->selectRaw('source_kind, source_id, loot_rule, sum(windows) as windows')->groupBy('source_kind', 'source_id', 'loot_rule');
    }

    /**
     * The rules over sources seen under two rules or more, the one with most corpses first. The free-for-all windows of a
     * source count as its arm counts them (`FreeForAllArms`).
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

        $shifted = $this->shifted($several, $source);
        $seen = array_map(fn (array $row): array => [
            'rule' => (string) $row['loot_rule'], 'sources' => (int) $row['sources'],
            'windows' => (int) $row['windows'] + ($row['loot_rule'] === LootRule::FreeForAll->value ? $shifted : 0),
        ], $rows);
        usort($seen, fn (array $a, array $b): int => [$b['windows'], $a['rule']] <=> [$a['windows'], $b['rule']]);

        return $seen;
    }

    /**
     * How many windows the arms add to the free-for-all windows of the sources in $several, the sources seen under two rules or more.
     */
    private function shifted(Builder $several, ?LootSourceRef $source): int
    {
        $shift = $this->counted->windowShift($source === null ? null : [$source], 'source', $source === null ? LootSourceKind::Creature : null);
        if ($shift === []) {
            return 0;
        }
        $armed = array_map(fn (string $key): LootSourceRef => new LootSourceRef(LootSourceKind::from(substr($key, 0, 1)), (int) substr($key, 1)), array_keys($shift));
        $total = 0;
        foreach ($this->read->rows(LootSourceRef::anyOf(clone $several, $armed, 's.source_kind', 's.source_id')) as $row) {
            $total += $shift[$row['source_kind'].$row['source_id']] ?? 0;
        }

        return $total;
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
     * @return array<string, array<string, array<string, array{unkeyed?: array<int, array{windows: int, items: int, squares: int}>, keyed?: array<int, array{windows: int, items: int, squares: int}>}>>> by source, rule, day (`''` outside free for all), whether the windows have a corpse key, and character
     */
    private function arms(?LootSourceRef $source, array $pair): array
    {
        $both = LootGroupScope::of(DB::table(LootCounted::WindowsCharacter->value), $source)
            ->whereIn('loot_rule', $pair)->select('source_kind', 'source_id')->groupBy('source_kind', 'source_id')->havingRaw('count(distinct loot_rule) = 2');
        $rows = $this->read->rows(LootGroupScope::of(DB::table(LootCounted::WindowsCharacter->value.' as t'), $source, 't.')
            ->whereIn('t.loot_rule', $pair)
            ->joinSub($both, 'b', fn (JoinClause $join) => $join->on('b.source_kind', '=', 't.source_kind')->on('b.source_id', '=', 't.source_id'))
            ->selectRaw('t.source_kind, t.source_id, t.loot_rule, t.character_id, t.keyed::int as keyed, '.LootDay::ofFreeForAll('t.loot_rule', 't.bucket').' as day, sum(t.windows) as windows, sum(t.item_rows) as items, sum(t.item_rows_squared) as squares')
            ->groupBy('t.source_kind', 't.source_id', 't.loot_rule', 't.character_id', 't.keyed')->groupByRaw(LootDay::ofFreeForAll('t.loot_rule', 't.bucket')));
        $by = [];
        foreach ($rows as $row) {
            $by[$row['source_kind'].$row['source_id']][(string) $row['loot_rule']][(string) $row['day']][(int) $row['keyed'] === 1 ? 'keyed' : 'unkeyed'][(int) $row['character_id']] = ['windows' => (int) $row['windows'], 'items' => (int) $row['items'], 'squares' => (int) $row['squares']];
        }

        return $by;
    }

    /**
     * @param  array<string, array<string, array<string, array{unkeyed?: array<int, array{windows: int, items: int, squares: int}>, keyed?: array<int, array{windows: int, items: int, squares: int}>}>>>  $sources
     * @param  list<string>  $pair
     * @param  array<string, list<string>>  $midnights  by source
     * @return array{0: array{difference: float, low: float, high: float, first: float, second: float, sources: int, windows: int}|null, 1: list<int>, 2: int}
     */
    private function pool(array $sources, array $pair, array $midnights): array
    {
        $pooled = [];
        $corpses = [0, 0];
        $more = 0;
        foreach ($sources as $source => $rules) {
            $armed = array_map(fn (string $rule): ?array => $this->arm($rule, $rules[$rule] ?? [], $midnights[(string) $source] ?? []), $pair);
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
     * The windows, item rows and their squares of one rule's arm on one source: each day, the corpses with a key, and of the
     * windows with no key every character's, or its one reader's (`LootArm::overDays`).
     *
     * @param  array<string, array{unkeyed?: array<int, array{windows: int, items: int, squares: int}>, keyed?: array<int, array{windows: int, items: int, squares: int}>}>  $days
     * @param  list<string>  $joined
     * @return array{n: int, sum: int, squares: int}|null
     */
    private function arm(string $rule, array $days, array $joined): ?array
    {
        $windows = fn (array $row): int => $row['windows'];
        $arm = LootArm::overDays(
            $rule, LootRule::words($rule),
            array_map(fn (array $parts): array => ['unkeyed' => array_map($windows, $parts['unkeyed'] ?? []), 'keyed' => array_map($windows, $parts['keyed'] ?? [])], $days),
            $rule === LootRule::FreeForAll->value, false, false, (int) config('scarlet.min_characters'), $joined,
        );
        if ($arm === null) {
            return null;
        }
        $sum = $squares = 0;
        foreach ($days as $day => $parts) {
            $counted = array_filter($parts['unkeyed'] ?? [], fn (int $character): bool => $arm->countsUnkeyedOn((string) $day, $character), ARRAY_FILTER_USE_KEY);
            $whole = $arm->countsKeyedOn((string) $day) ? $parts['keyed'] ?? [] : [];
            $sum += array_sum(array_column($counted, 'items')) + array_sum(array_column($whole, 'items'));
            $squares += array_sum(array_column($counted, 'squares')) + array_sum(array_column($whole, 'squares'));
        }

        return ['n' => $arm->windows, 'sum' => $sum, 'squares' => $squares];
    }
}
