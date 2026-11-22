<?php

namespace App\Actions\Public;

use App\Actions\Rollups\SourceDrops;
use App\Support\LootCounted;
use App\Support\LootListing;
use App\Support\LootRead;
use App\Support\LootRule;
use App\Support\LootSourceKind;
use App\Support\NameKind;
use App\Support\RollupRead;
use Illuminate\Support\Facades\DB;

class ObjectRecord
{
    public function __construct(
        private readonly RollupRead $read,
        private readonly SourceDrops $dropTable,
        private readonly LootPoints $points,
        private readonly MapZones $zones,
    ) {}

    /**
     * Everything the object page and `/public/objects/{id}` show: what the object is called (null until the addon has
     * named it), how many times it was opened for the first time, by how many characters, where, at which looter levels,
     * what it gives (its drop table over the read it has most windows of, narrowed to `$rule` when that is a rule the
     * table was read under) and where it was opened. Every ruleset together, and personal loot out. Null when no window
     * of an object with this id is recorded, or fewer than `scarlet.min_characters` characters read it.
     *
     * @return array<string, mixed>|null
     */
    public function handle(int $id, ?string $rule = null): ?array
    {
        $kind = LootSourceKind::Object->value;
        $seen = $this->seen($kind, $id);
        if ($seen === null) {
            return null;
        }
        $reads = $this->reads($kind, $id);
        $read = LootRead::tryFrom((string) array_key_first($reads)) ?? LootRead::Loot;
        $items = $this->items($kind, $id);

        return [
            'id' => $id,
            'name' => DB::table('names')->where('kind', NameKind::Object->value)->where('id', $id)->orderByDesc('votes')->orderBy('name')->value('name'),
            'read' => $read->value,
            'windows' => array_sum($reads),
            'characters' => $seen['characters'],
            'items' => $items,
            'zones' => $this->zonesOf($kind, $id),
            ...$this->levels($kind, $id),
            'drop_table' => $this->dropTable->handle($kind, $id, $rule, $read),
            'points' => $this->points->handle($kind, $id),
            'map_zones' => $this->zones->handle(),
        ];
    }

    /**
     * @return array{windows: int, characters: int}|null
     */
    private function seen(string $kind, int $id): ?array
    {
        $row = $this->read->rows($this->read->shown(
            $this->read->table(LootCounted::Windows->value, null)
                ->selectRaw('sum(windows) as windows, distinct_count(rollup(characters)) as characters')
                ->where('source_kind', $kind)->where('source_id', $id),
        ))[0] ?? null;

        return $row === null || $row['windows'] === null ? null : ['windows' => (int) $row['windows'], 'characters' => (int) round((float) $row['characters'])];
    }

    /**
     * The windows of each read an object has a page for, most first.
     *
     * @return array<string, int>
     */
    private function reads(string $kind, int $id): array
    {
        $rows = $this->read->rows($this->read->shown(LootRule::within(LootListing::ofObjects($this->read->table(LootCounted::Windows->value, null)), 'loot_rule', null)
            ->selectRaw('read, sum(windows) as windows')->where('source_kind', $kind)->where('source_id', $id)
            ->groupBy('read')->orderByRaw('sum(windows) desc')->orderBy('read')));

        return array_column(array_map(fn (array $row): array => [(string) $row['read'], (int) $row['windows']], $rows), 1, 0);
    }

    private function items(string $kind, int $id): int
    {
        $row = $this->read->rows($this->read->shown(LootRule::within(LootListing::ofObjects($this->read->table(LootCounted::Items->value, null)), 'loot_rule', null)
            ->selectRaw('count(distinct item_id) as items')->where('source_kind', $kind)->where('source_id', $id)))[0] ?? null;

        return $row === null ? 0 : (int) $row['items'];
    }

    /**
     * @return list<array{id: int, name: string|null, windows: int}>
     */
    private function zonesOf(string $kind, int $id): array
    {
        $rows = $this->read->rows($this->read->shown(LootRule::within(LootListing::ofObjects($this->read->table(LootCounted::Windows->value, null)), 'loot_rule', null)
            ->selectRaw('zone_id, (select z.name from zones z where z.id = zone_id) as zone, sum(windows) as windows')
            ->where('source_kind', $kind)->where('source_id', $id)->whereNotNull('zone_id')
            ->groupBy('zone_id')->orderByRaw('sum(windows) desc')->orderBy('zone_id')->limit(20)));

        return array_map(fn (array $row): array => ['id' => (int) $row['zone_id'], 'name' => $row['zone'], 'windows' => (int) $row['windows']], $rows);
    }

    /**
     * The levels of the characters that opened it.
     *
     * @return array{level_min: int|null, level_max: int|null}
     */
    private function levels(string $kind, int $id): array
    {
        $row = $this->read->rows(DB::table('loot_opens')
            ->selectRaw('min(level) as level_min, max(level) as level_max')
            ->where('counted', true)->where('has_items', true)->whereRaw('first_read is not false')
            ->where('source_kind', $kind)->where('source_id', $id))[0] ?? null;

        $low = $row['level_min'] ?? null;
        $high = $row['level_max'] ?? null;

        return ['level_min' => $low === null ? null : (int) $low, 'level_max' => $high === null ? null : (int) $high];
    }
}
