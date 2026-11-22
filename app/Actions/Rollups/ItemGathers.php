<?php

namespace App\Actions\Rollups;

use App\Support\GatherKind;
use App\Support\LootSourceKind;
use App\Support\NameKind;
use App\Support\Rollup;
use App\Support\RollupRead;

class ItemGathers
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * Where an item is gathered, one row per source: the creature skinned, the container the catch came in, or the
     * profession itself when the source is a herb node or a vein with no name of its own. `rate` is the share of a
     * creature's skins that gave the item, over every ruleset. Gathering answers cover every ruleset, so no ruleset
     * narrows this. Every shown row comes back, most gathers first, so a caller can total them before it trims.
     *
     * @return list<array{kind: string, source_kind: string|null, source_id: int|null, source: string|null, gathers: int, quantity: int, characters: int, median_skill: float|null, rate: float|null}>
     */
    public function handle(int $itemId): array
    {
        $rows = [...$this->skins($itemId), ...$this->containers($itemId), ...$this->professions($itemId)];
        usort($rows, fn (array $a, array $b): int => [$b['gathers'], $a['kind']] <=> [$a['gathers'], $b['kind']]);

        return $rows;
    }

    /** @return list<array{kind: string, source_kind: string, source_id: int, source: string|null, gathers: int, quantity: int, characters: int, median_skill: float|null, rate: float|null}> */
    private function skins(int $itemId): array
    {
        $query = $this->read->from(Rollup::GatherItemsDailySource, null)
            ->where('item_id', $itemId)
            ->where('kind', GatherKind::Skin->value)
            ->whereNull('container_item_id')
            ->whereNotNull('source_id')
            ->selectRaw('source_id, (select n.name from names n where n.kind = ? and n.id = source_id order by n.votes desc, n.name limit 1) as source, sum(items) as gathers, sum(quantity) as quantity, distinct_count(rollup(characters)) as characters, approx_percentile(0.5, rollup(skill_digest)) as median_skill', [NameKind::Npc->value])
            ->groupBy('source_id');
        $rows = $this->read->rows($this->read->shown($query));
        $looted = $this->looted(array_map(fn (array $row): int => (int) $row['source_id'], $rows));

        return array_map(fn (array $row): array => [
            'kind' => GatherKind::Skin->value,
            'source_kind' => LootSourceKind::Creature->value,
            'source_id' => (int) $row['source_id'],
            'source' => $row['source'] === null ? null : (string) $row['source'],
            'gathers' => (int) $row['gathers'],
            'quantity' => (int) $row['quantity'],
            'characters' => (int) $row['characters'],
            'median_skill' => $row['median_skill'] === null ? null : (float) $row['median_skill'],
            'rate' => ($looted[(int) $row['source_id']] ?? 0) > 0 ? (int) $row['gathers'] / $looted[(int) $row['source_id']] : null,
        ], $rows);
    }

    /**
     * The skins of each creature that were looted, from the gather attempts, so a skin row can hold a rate.
     *
     * @param  list<int>  $creatures
     * @return array<int, int> creature id => skins looted
     */
    private function looted(array $creatures): array
    {
        if ($creatures === []) {
            return [];
        }
        $rows = $this->read->rows(
            $this->read->from(Rollup::GathersDailySourceRuleset, null)
                ->where('kind', GatherKind::Skin->value)
                ->whereIn('source_id', $creatures)
                ->selectRaw('source_id, sum(looted) as looted')
                ->groupBy('source_id'),
        );

        return array_map(fn (array $row): int => (int) $row['looted'], array_column($rows, null, 'source_id'));
    }

    /** @return list<array{kind: string, source_kind: string, source_id: int, source: string|null, gathers: int, quantity: int, characters: int, median_skill: float|null, rate: null}> */
    private function containers(int $itemId): array
    {
        $query = $this->read->from(Rollup::GatherItemsDailySource, null)
            ->where('item_id', $itemId)
            ->whereNotNull('container_item_id')
            ->selectRaw('kind, container_item_id, (select n.name from names n where n.kind = ? and n.id = container_item_id order by n.votes desc, n.name limit 1) as source, sum(items) as gathers, sum(quantity) as quantity, distinct_count(rollup(characters)) as characters, approx_percentile(0.5, rollup(skill_digest)) as median_skill', [NameKind::Item->value])
            ->groupBy('kind', 'container_item_id');
        $rows = $this->read->rows($this->read->shown($query));

        return array_map(fn (array $row): array => [
            'kind' => (string) $row['kind'],
            'source_kind' => LootSourceKind::Bag->value,
            'source_id' => (int) $row['container_item_id'],
            'source' => $row['source'] === null ? null : (string) $row['source'],
            'gathers' => (int) $row['gathers'],
            'quantity' => (int) $row['quantity'],
            'characters' => (int) $row['characters'],
            'median_skill' => $row['median_skill'] === null ? null : (float) $row['median_skill'],
            'rate' => null,
        ], $rows);
    }

    /** @return list<array{kind: string, source_kind: null, source_id: null, source: null, gathers: int, quantity: int, characters: int, median_skill: float|null, rate: null}> */
    private function professions(int $itemId): array
    {
        $query = $this->read->from(Rollup::GatherItemsDailySource, null)
            ->where('item_id', $itemId)
            ->whereNull('container_item_id')
            ->where(fn ($query) => $query->where('kind', '!=', GatherKind::Skin->value)->orWhereNull('source_id'))
            ->selectRaw('kind, sum(items) as gathers, sum(quantity) as quantity, distinct_count(rollup(characters)) as characters, approx_percentile(0.5, rollup(skill_digest)) as median_skill')
            ->groupBy('kind');
        $rows = $this->read->rows($this->read->shown($query));

        return array_map(fn (array $row): array => [
            'kind' => (string) $row['kind'],
            'source_kind' => null,
            'source_id' => null,
            'source' => null,
            'gathers' => (int) $row['gathers'],
            'quantity' => (int) $row['quantity'],
            'characters' => (int) $row['characters'],
            'median_skill' => $row['median_skill'] === null ? null : (float) $row['median_skill'],
            'rate' => null,
        ], $rows);
    }
}
