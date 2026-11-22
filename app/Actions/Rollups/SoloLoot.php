<?php

namespace App\Actions\Rollups;

use App\Support\LootRead;
use App\Support\LootSourceKind;
use App\Support\RateEstimate;
use App\Support\RateFlag;
use App\Support\Rollup;
use App\Support\RollupRead;
use Illuminate\Support\Facades\DB;

class SoloLoot
{
    /** A group of 0 or 1 is solo play: nobody else may open the corpse. */
    private const array SOLO_SIZES = [0, 1];

    public function __construct(private readonly RollupRead $read) {}

    /**
     * What a creature gives to a character playing alone, set against the kills that character got credit for: the
     * windows read for loot at a group size of 0 or 1 over the creature's solo kills, and the same for each item those
     * windows listed. It is a lower bound on a drop rate, since a kill with no window is an empty corpse or one nobody
     * looted. Kills and windows come from sessions with `combat` and `loot` parts after item lists began
     * (docs/LOOT.md, Metrics). `estimate` is null while the creature has no solo kills.
     *
     * @return array{kills: int, windows: int, estimate: RateEstimate|null, rows: list<array{item_id: int, item: string|null, estimate: RateEstimate}>}
     */
    public function handle(int $npcId): array
    {
        $kills = $this->kills($npcId);
        if ($kills === null) {
            return ['kills' => 0, 'windows' => 0, 'estimate' => null, 'rows' => []];
        }
        $flags = RateFlag::tags($kills['characters'], false);
        $windows = $this->windows($npcId);
        $rows = array_map(fn (array $row): array => [
            'item_id' => (int) $row['item_id'], 'item' => $row['item'],
            'estimate' => RateEstimate::of((int) $row['windows'], $kills['kills'], $flags, RateEstimate::UNIT_KILL),
        ], $this->items($npcId));
        usort($rows, fn (array $a, array $b): int => RateEstimate::compare($a['estimate'], $b['estimate']) ?: $a['item_id'] <=> $b['item_id']);

        return ['kills' => $kills['kills'], 'windows' => $windows, 'estimate' => RateEstimate::of($windows, $kills['kills'], $flags, RateEstimate::UNIT_KILL), 'rows' => $rows];
    }

    /**
     * @return array{kills: int, characters: int}|null
     */
    private function kills(int $npcId): ?array
    {
        $row = $this->read->rows(DB::table(Rollup::KillsSoloDaily->value)
            ->selectRaw('sum(kills) as kills, distinct_count(rollup(characters)) as characters')
            ->where('npc_id', $npcId)
            ->havingRaw('distinct_count(rollup(characters)) >= ?', [(int) config('scarlet.min_characters')]))[0] ?? null;

        return $row === null || $row['kills'] === null ? null : ['kills' => (int) $row['kills'], 'characters' => (int) round((float) $row['characters'])];
    }

    private function windows(int $npcId): int
    {
        $row = $this->read->rows(LootRead::Loot->within(DB::table(Rollup::LootWindowsDaily->value))
            ->selectRaw('sum(windows) as windows')
            ->where('source_kind', LootSourceKind::Creature->value)->where('source_id', $npcId)
            ->whereIn('group_size', self::SOLO_SIZES)
            ->havingRaw('distinct_count(rollup(characters)) >= ?', [(int) config('scarlet.min_characters')]))[0] ?? null;

        return $row === null ? 0 : (int) $row['windows'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function items(int $npcId): array
    {
        return $this->read->rows(LootRead::Loot->within(DB::table(Rollup::LootItemsDaily->value.' as d'), 'd.read')
            ->selectRaw("d.item_id, (select n.name from names n where n.kind = 'item' and n.id = d.item_id order by n.votes desc, n.name limit 1) as item")
            ->selectRaw('sum(d.windows) as windows')
            ->where('d.source_kind', LootSourceKind::Creature->value)->where('d.source_id', $npcId)
            ->whereIn('d.group_size', self::SOLO_SIZES)
            ->groupBy('d.item_id')
            ->havingRaw('distinct_count(rollup(d.characters)) >= ?', [(int) config('scarlet.min_characters')]));
    }
}
