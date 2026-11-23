<?php

namespace App\Actions\Rollups;

use App\Support\LootCounted;
use App\Support\LootDay;
use App\Support\LootRead;
use App\Support\LootRule;
use App\Support\LootSourceRef;
use App\Support\Rollup;
use App\Support\RollupRead;
use App\Support\SourceArmData;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class SourceArmRows
{
    public function __construct(private readonly RollupRead $read, private readonly MidnightWindows $midnights) {}

    /**
     * The rows behind the arms of every source in the list, one read of each table for all of them: the windows by rule, size,
     * character and whether a corpse key counted them, the items by rule and size, and, for a source an arm of which may
     * count one reader, the windows with no key that listed each item by character (docs/LOOT.md, Units of counting,
     * rule 6). The free-for-all rows are also by day, since that arm counts one set only on the days where a reader and
     * another character overlap (`LootArm::overDays`); every other row has the day `''`. A source with no window has empty rows.
     *
     * @param  list<LootSourceRef>  $sources
     * @return array<string, SourceArmData> by `LootSourceRef::key()`
     */
    public function handle(array $sources): array
    {
        $unique = [];
        foreach ($sources as $source) {
            $unique[$source->key()] = $source;
        }
        $sources = array_values($unique);
        if ($sources === []) {
            return [];
        }

        $windows = $this->windows($sources);
        $items = $this->items($sources);
        $readers = $this->readers(array_values(array_filter($sources, fn (LootSourceRef $source): bool => $this->mayHaveReader($windows[$source->key()] ?? []))));

        $midnights = $this->midnights->handle($sources);
        $answer = [];
        foreach ($sources as $source) {
            $answer[$source->key()] = new SourceArmData($windows[$source->key()] ?? [], $items[$source->key()] ?? [], $readers[$source->key()] ?? [], $midnights[$source->key()] ?? []);
        }

        return $answer;
    }

    /**
     * @param  list<LootSourceRef>  $sources
     * @return array<string, list<array{loot_rule: string, size: int|null, character: int, keyed: bool, day: string, windows: int}>>
     */
    private function windows(array $sources): array
    {
        $rows = $this->read->rows($this->within(LootRead::Loot->within(DB::table(LootCounted::WindowsCharacter->value)), $sources, 'source_kind', 'source_id')
            ->selectRaw('source_kind, source_id, loot_rule, group_size, character_id, keyed::int as keyed, '.LootDay::ofFreeForAll().' as day, sum(windows) as windows')
            ->groupBy('source_kind', 'source_id', 'loot_rule', 'group_size', 'character_id', 'keyed')->groupByRaw(LootDay::ofFreeForAll()));

        $by = [];
        foreach ($rows as $row) {
            $by[$row['source_kind'].$row['source_id']][] = [
                'loot_rule' => (string) $row['loot_rule'], 'size' => $row['group_size'] === null ? null : max(1, (int) $row['group_size']),
                'character' => (int) $row['character_id'], 'keyed' => (int) $row['keyed'] === 1, 'day' => (string) $row['day'], 'windows' => (int) $row['windows'],
            ];
        }

        return $by;
    }

    /**
     * @param  list<LootSourceRef>  $sources
     * @return array<string, list<array<string, mixed>>> rows of item_id, item, bind_type, loot_rule, group_size, keyed, day, drops
     */
    private function items(array $sources): array
    {
        $rows = $this->read->rows($this->within(LootRead::Loot->within(DB::table(LootCounted::Items->value.' as d'), 'd.read'), $sources, 'd.source_kind', 'd.source_id')
            ->leftJoin('items as i', 'i.item_id', '=', 'd.item_id')
            ->selectRaw("d.source_kind, d.source_id, d.item_id, (select n.name from names n where n.kind = 'item' and n.id = d.item_id order by n.votes desc, n.name limit 1) as item")
            ->selectRaw('i.bind_type, d.loot_rule, d.group_size, d.keyed::int as keyed, '.LootDay::ofFreeForAll('d.loot_rule', 'd.bucket').' as day, sum(d.windows) as drops')
            ->groupBy('d.source_kind', 'd.source_id', 'd.item_id', 'i.bind_type', 'd.loot_rule', 'd.group_size', 'd.keyed')->groupByRaw(LootDay::ofFreeForAll('d.loot_rule', 'd.bucket')));

        $by = [];
        foreach ($rows as $row) {
            $by[$row['source_kind'].$row['source_id']][] = $row;
        }

        return $by;
    }

    /**
     * Whether an arm of the source may count one reader (`LootArm::overDays`): its free-for-all windows have some with no
     * corpse key and come from two characters or more. An arm of one size holds some of the windows of the source, so it
     * never has a reader when the source does not.
     *
     * @param  list<array{loot_rule: string, size: int|null, character: int, keyed: bool, day: string, windows: int}>  $windows
     */
    private function mayHaveReader(array $windows): bool
    {
        $unkeyed = $all = [];
        foreach ($windows as $row) {
            if ($row['loot_rule'] === LootRule::FreeForAll->value) {
                $all[$row['character']] = true;
                $unkeyed[$row['character']] = ($unkeyed[$row['character']] ?? false) || ! $row['keyed'];
            }
        }

        return in_array(true, $unkeyed, true) && count($all) >= 2;
    }

    /**
     * @param  list<LootSourceRef>  $sources
     * @return array<string, array<int, array<int, array<string, array<int, int>>>>> by source, then item, size, day and character
     */
    private function readers(array $sources): array
    {
        if ($sources === []) {
            return [];
        }
        $rows = $this->read->rows($this->within(LootRead::Loot->within(DB::table(Rollup::LootFreeForAllItemsUnkeyedDaily->value)), $sources, 'source_kind', 'source_id')
            ->selectRaw('source_kind, source_id, item_id, group_size, character_id, '.LootDay::of().' as day, sum(windows) as windows')
            ->groupBy('source_kind', 'source_id', 'item_id', 'group_size', 'character_id')->groupByRaw(LootDay::of()));

        $by = [];
        foreach ($rows as $row) {
            $key = $row['source_kind'].$row['source_id'];
            $size = $row['group_size'] === null ? 0 : max(1, (int) $row['group_size']);
            [$item, $day, $character] = [(int) $row['item_id'], (string) $row['day'], (int) $row['character_id']];
            $by[$key][$item][$size][$day][$character] = ($by[$key][$item][$size][$day][$character] ?? 0) + (int) $row['windows'];
        }

        return $by;
    }

    /**
     * The rows of the listed sources: each kind with its ids, so a read of one source filters as it always did.
     *
     * @param  list<LootSourceRef>  $sources
     */
    private function within(Builder $query, array $sources, string $kind, string $id): Builder
    {
        $ids = [];
        foreach ($sources as $source) {
            $ids[$source->kind->value][] = $source->id;
        }

        return $query->where(function (Builder $any) use ($ids, $kind, $id): void {
            foreach ($ids as $value => $of) {
                $any->orWhere(fn (Builder $one): Builder => $one->where($kind, $value)->whereIn($id, $of));
            }
        });
    }
}
