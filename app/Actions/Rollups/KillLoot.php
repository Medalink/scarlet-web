<?php

namespace App\Actions\Rollups;

use App\Support\KeyedKills;
use App\Support\LootRead;
use App\Support\LootRule;
use App\Support\LootSourceKind;
use App\Support\RateEstimate;
use App\Support\RateFlag;
use App\Support\Rollup;
use App\Support\RollupRead;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class KillLoot
{
    /** A group of 0 or 1 is solo play: nobody else may open the corpse. */
    private const array SOLO_SIZES = [0, 1];

    public function __construct(private readonly RollupRead $read) {}

    /**
     * What a creature gives, set against the kills of its corpses (docs/LOOT.md, Metrics, Corpses looted of kills): the
     * corpses read for loot over the kills, and the same for each item those corpses listed. A kill with a corpse key
     * is one corpse whoever got the credit and at any group size; it is looted when a window kept for that corpse was
     * read for loot. Kills with no key are the credited kills of a character playing alone, set against the windows
     * read for loot at a group size of 0 or 1, and only under all rules or solo. `$rule` narrows the kills with a key to
     * one loot rule (without it, every rule but personal loot) and `$soloOnly` to a group size of 0 or 1. It is a lower
     * bound on a drop rate, since a kill with no window is an empty corpse or one nobody looted. Kills and windows
     * come from sessions with `combat` and `loot` parts after item lists began. `estimate` is null while the creature
     * has no such kills.
     *
     * @return array{kills: int, windows: int, estimate: RateEstimate|null, rows: list<array{item_id: int, item: string|null, estimate: RateEstimate}>}
     */
    public function handle(int $npcId, ?string $rule = null, bool $soloOnly = false): array
    {
        $kills = $this->kills($npcId, $rule, $soloOnly);
        if ($kills === null) {
            return ['kills' => 0, 'windows' => 0, 'estimate' => null, 'rows' => []];
        }
        $flags = RateFlag::tags($kills['characters'], false);
        $windows = $kills['looted'] + $this->unkeyedWindows($npcId, $rule);
        $rows = array_map(fn (array $row): array => [
            'item_id' => (int) $row['item_id'], 'item' => $row['item'],
            'estimate' => RateEstimate::of((int) $row['windows'], $kills['kills'], $flags, RateEstimate::UNIT_KILL),
        ], $this->items($npcId, $rule, $soloOnly));
        usort($rows, fn (array $a, array $b): int => RateEstimate::compare($a['estimate'], $b['estimate']) ?: $a['item_id'] <=> $b['item_id']);

        return ['kills' => $kills['kills'], 'windows' => $windows, 'estimate' => RateEstimate::of($windows, $kills['kills'], $flags, RateEstimate::UNIT_KILL), 'rows' => $rows];
    }

    /** The kills with no key are solo play, so they belong to all rules and to solo. */
    private function withUnkeyed(?string $rule): bool
    {
        return $rule === null || $rule === LootRule::Solo->value;
    }

    private function keyed(Builder $query, ?string $rule, bool $soloOnly): Builder
    {
        LootRule::within($query, 'loot_rule', $rule);

        return $soloOnly ? $query->whereIn('group_size', self::SOLO_SIZES) : $query;
    }

    /**
     * @return array{kills: int, looted: int, characters: int}|null
     */
    private function kills(int $npcId, ?string $rule, bool $soloOnly): ?array
    {
        $keyed = $this->keyed(DB::table(KeyedKills::Kills->value), $rule, $soloOnly)->selectRaw('kills, looted, characters')->where('npc_id', $npcId);
        $union = $this->withUnkeyed($rule) ? DB::table(Rollup::KillsSoloUnkeyedDaily->value)->selectRaw('kills, 0::bigint as looted, characters')->where('npc_id', $npcId)->unionAll($keyed) : $keyed;
        $row = $this->read->rows(DB::query()->fromSub($union, 'k')
            ->selectRaw('sum(kills) as kills, sum(looted) as looted, distinct_count(rollup(characters)) as characters')
            ->havingRaw('distinct_count(rollup(characters)) >= ?', [(int) config('scarlet.min_characters')]))[0] ?? null;

        return $row === null || $row['kills'] === null ? null : ['kills' => (int) $row['kills'], 'looted' => (int) $row['looted'], 'characters' => (int) round((float) $row['characters'])];
    }

    private function unkeyedWindows(int $npcId, ?string $rule): int
    {
        if (! $this->withUnkeyed($rule)) {
            return 0;
        }
        $row = $this->read->rows(LootRead::Loot->within(DB::table(Rollup::LootWindowsUnkeyedDaily->value))
            ->selectRaw('sum(windows) as windows')
            ->where('source_kind', LootSourceKind::Creature->value)->where('source_id', $npcId)
            ->whereIn('group_size', self::SOLO_SIZES)
            ->havingRaw('distinct_count(rollup(characters)) >= ?', [(int) config('scarlet.min_characters')]))[0] ?? null;

        return $row === null ? 0 : (int) $row['windows'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function items(int $npcId, ?string $rule, bool $soloOnly): array
    {
        $keyed = $this->keyed(DB::table(KeyedKills::Items->value), $rule, $soloOnly)->selectRaw('item_id, windows, characters')->where('npc_id', $npcId);
        $union = $this->withUnkeyed($rule)
            ? LootRead::Loot->within(DB::table(Rollup::LootItemsUnkeyedDaily->value))->selectRaw('item_id, windows, characters')
                ->where('source_kind', LootSourceKind::Creature->value)->where('source_id', $npcId)->whereIn('group_size', self::SOLO_SIZES)->unionAll($keyed)
            : $keyed;

        return $this->read->rows(DB::query()->fromSub($union, 'd')
            ->selectRaw("d.item_id, (select n.name from names n where n.kind = 'item' and n.id = d.item_id order by n.votes desc, n.name limit 1) as item")
            ->selectRaw('sum(d.windows) as windows')
            ->groupBy('d.item_id')
            ->havingRaw('distinct_count(rollup(d.characters)) >= ?', [(int) config('scarlet.min_characters')]));
    }
}
