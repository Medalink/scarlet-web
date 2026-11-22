<?php

namespace App\Actions\Rollups;

use App\Support\ArmSplit;
use App\Support\LootArm;
use App\Support\LootRead;
use App\Support\LootRule;
use App\Support\LootSourceKind;
use App\Support\LootSourceRef;
use App\Support\NameKind;
use App\Support\RateDifference;
use App\Support\RateEstimate;
use App\Support\RateSample;
use App\Support\Rollup;
use App\Support\RollupRead;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class LootMarks
{
    /** The most sources and splits checked: each is a read of one source's drop table. */
    public const int CHECKED = 150;

    public function __construct(private readonly RollupRead $read, private readonly SourceRuleArms $arms) {}

    /**
     * Every marked difference (docs/LOOT.md, Statistical treatment, What a mark means): an item whose rate under one loot
     * rule, or at one group size inside a rule, differs from the other arm's by a range that stays off zero. The game's
     * drop table depends on neither, so each is a counting fault until shown otherwise and is listed for a developer. A
     * source is checked when it has 30 corpses or more under two rules that are not personal loot, or under two group
     * sizes inside one rule; the `CHECKED` splits with the most corpses are read. The largest difference comes first.
     *
     * @return list<array{source: LootSourceRef, name: string|null, split: ArmSplit, rule: string|null, item_id: int, item: string|null, pair: list<LootArm>, cells: list<array{arm: LootArm, estimate: RateEstimate}>, difference: RateDifference}>
     */
    public function handle(): array
    {
        $marks = [];
        foreach ($this->candidates() as $candidate) {
            $answer = $this->arms->handle($candidate['source']->kind->value, $candidate['source']->id, $candidate['split'], $candidate['rule']);
            foreach ($answer['rows'] as $row) {
                if ($row['difference'] !== null && $row['difference']->marked) {
                    $marks[] = ['source' => $candidate['source'], 'name' => null, 'split' => $candidate['split'], 'rule' => $answer['rule'], 'item_id' => $row['item_id'], 'item' => $row['item'], 'pair' => $answer['pair'], 'cells' => $row['cells'], 'difference' => $row['difference']];
                }
            }
        }
        usort($marks, fn (array $a, array $b): int => abs($b['difference']->points) <=> abs($a['difference']->points));
        $names = $this->names($marks);

        return array_map(fn (array $mark): array => [...$mark, 'name' => $names[$mark['source']->key()] ?? null], $marks);
    }

    /**
     * @return list<array{source: LootSourceRef, split: ArmSplit, rule: string|null}>
     */
    private function candidates(): array
    {
        $byRule = $this->read->rows($this->windows()->where('loot_rule', '<>', LootRule::Personal->value)
            ->selectRaw('source_kind, source_id, loot_rule, sum(windows) as windows')->groupBy('source_kind', 'source_id', 'loot_rule')
            ->havingRaw('sum(windows) >= ?', [RateSample::SOLID_FROM]));
        $bySize = $this->read->rows($this->windows()->whereNotNull('group_size')
            ->selectRaw('source_kind, source_id, loot_rule, group_size, sum(windows) as windows')->groupBy('source_kind', 'source_id', 'loot_rule', 'group_size')
            ->havingRaw('sum(windows) >= ?', [RateSample::SOLID_FROM]));

        $found = [];
        foreach ($byRule as $row) {
            $key = $row['source_kind'].$row['source_id'];
            $found[$key] ??= ['source' => new LootSourceRef(LootSourceKind::from((string) $row['source_kind']), (int) $row['source_id']), 'split' => ArmSplit::Rule, 'rule' => null, 'arms' => 0, 'windows' => 0];
            $found[$key]['arms']++;
            $found[$key]['windows'] += (int) $row['windows'];
        }
        foreach ($bySize as $row) {
            $key = $row['source_kind'].$row['source_id'].'|'.$row['loot_rule'];
            $found[$key] ??= ['source' => new LootSourceRef(LootSourceKind::from((string) $row['source_kind']), (int) $row['source_id']), 'split' => ArmSplit::Size, 'rule' => (string) $row['loot_rule'], 'arms' => 0, 'windows' => 0];
            $found[$key]['arms']++;
            $found[$key]['windows'] += (int) $row['windows'];
        }
        $found = array_values(array_filter($found, fn (array $split): bool => $split['arms'] >= 2));
        usort($found, fn (array $a, array $b): int => $b['windows'] <=> $a['windows']);

        return array_map(fn (array $split): array => ['source' => $split['source'], 'split' => $split['split'], 'rule' => $split['rule']], array_slice($found, 0, self::CHECKED));
    }

    private function windows(): Builder
    {
        return LootRead::Loot->within(DB::table(Rollup::LootWindowsCharacterDaily->value))
            ->whereIn('source_kind', [LootSourceKind::Creature->value, LootSourceKind::Object->value])->whereNotNull('source_id');
    }

    /**
     * The dictionary names of the marked sources, by source key.
     *
     * @param  list<array{source: LootSourceRef}>  $marks
     * @return array<string, string>
     */
    private function names(array $marks): array
    {
        $names = [];
        foreach ([LootSourceKind::Creature, LootSourceKind::Object] as $kind) {
            $ids = array_values(array_unique(array_map(fn (array $mark): int => $mark['source']->id, array_filter($marks, fn (array $mark): bool => $mark['source']->kind === $kind))));
            if ($ids === []) {
                continue;
            }
            $dictionary = $kind === LootSourceKind::Creature ? NameKind::Npc : NameKind::Object;
            foreach (DB::table('names')->where('kind', $dictionary->value)->whereIn('id', $ids)->orderByDesc('votes')->orderBy('name')->get(['id', 'name']) as $row) {
                $names[$kind->value.$row->id] ??= (string) $row->name;
            }
        }

        return $names;
    }
}
