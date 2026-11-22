<?php

namespace App\Actions\Public\Character;

use App\Support\GameObjects;
use App\Support\KillerKind;
use App\Support\LootSourceKind;
use App\Support\LootVia;
use App\Support\MovementMode;
use App\Support\NameKind;
use App\Support\PlayerNameShape;
use Illuminate\Support\Facades\DB;

class Records
{
    /**
     * The facts a character holds a record in, each null or zero when nothing was recorded. The biggest blow is the
     * biggest one a creature dealt: a death with no known killer, or one by a player, is not a candidate. `nodes` counts
     * gathering windows opened on an object other than the fishing bobber, `fish` those opened on the bobber. `topNode`
     * and `topFish` are the item taken most from each, by quantity, and `topNode` leaves out the items the bobber gave.
     * `jumps` and `swam` are the movement stretches' counts, `topEmote` the emote used most and `modes` the yards by
     * movement mode, most first.
     *
     * @return array{
     *     blow: array{damage: int, level: int|null, zone_id: int|null, zone: string|null, killer: string}|null,
     *     nodes: int, fish: int, jumps: int, swam: int,
     *     topNode: array{item_id: int, name: string, quantity: int}|null,
     *     topFish: array{item_id: int, name: string, quantity: int}|null,
     *     topEmote: array{token: string, uses: int}|null,
     *     modes: list<array{mode: string, yards: int}>
     * }
     */
    public function handle(int $characterId): array
    {
        $gathered = DB::table('loot_opens')->where('character_id', $characterId)->where('counted', true)->where('source_kind', LootSourceKind::Object->value)
            ->selectRaw('count(*) filter (where source_id is distinct from ?) as nodes, count(*) filter (where source_id = ?) as fish', [GameObjects::FISHING_BOBBER, GameObjects::FISHING_BOBBER])->first();
        $nodes = (int) ($gathered->nodes ?? 0);
        $fish = (int) ($gathered->fish ?? 0);
        $moved = DB::table('movement_stretches')->where('character_id', $characterId)->where('counted', true)
            ->selectRaw('coalesce(sum(jumps), 0) as jumps, coalesce(sum(yards) filter (where mode = ?), 0) as swam', [MovementMode::Swim->value])->first();

        return [
            'blow' => $this->blow($characterId),
            'nodes' => $nodes,
            'fish' => $fish,
            'jumps' => (int) ($moved->jumps ?? 0),
            'swam' => (int) ($moved->swam ?? 0),
            'topNode' => $nodes > 0 ? $this->topTaken($characterId, false) : null,
            'topFish' => $fish > 0 ? $this->topTaken($characterId, true) : null,
            'topEmote' => $this->topEmote($characterId),
            'modes' => $this->modes($characterId),
        ];
    }

    /** @return list<array{mode: string, yards: int}> */
    private function modes(int $characterId): array
    {
        return array_values(DB::table('movement_stretches')->where('character_id', $characterId)->where('counted', true)->whereNotNull('mode')
            ->groupBy('mode')->selectRaw('mode, sum(yards)::bigint as yards')->orderByDesc('yards')->get()
            ->map(fn (object $row): array => ['mode' => (string) $row->mode, 'yards' => (int) $row->yards])->all());
    }

    /** @return array{token: string, uses: int}|null */
    private function topEmote(int $characterId): ?array
    {
        $row = DB::table('emotes')->where('character_id', $characterId)->where('counted', true)->whereNotNull('token')
            ->groupBy('token')->selectRaw('token, count(*) as uses')->orderByDesc('uses')->orderBy('token')->first();

        return $row === null ? null : ['token' => (string) $row->token, 'uses' => (int) $row->uses];
    }

    /** @return array{damage: int, level: int|null, zone_id: int|null, zone: string|null, killer: string}|null */
    private function blow(int $characterId): ?array
    {
        $deaths = DB::table('deaths')->where('character_id', $characterId)->where('damage', '>', 0)->whereNotNull('killer')->orderByDesc('damage')->orderBy('at')
            ->selectRaw('damage, level, zone_id, (select z.name from zones z where z.id = deaths.zone_id) as zone, killer_kind, killer');
        foreach ($deaths->cursor() as $death) {
            if ($death->killer_kind === KillerKind::Player->value || PlayerNameShape::matches($death->killer)) {
                continue;
            }

            return [
                'damage' => (int) $death->damage, 'level' => $death->level === null ? null : (int) $death->level,
                'zone_id' => $death->zone_id === null ? null : (int) $death->zone_id, 'zone' => $death->zone, 'killer' => $death->killer,
            ];
        }

        return null;
    }

    /** @return array{item_id: int, name: string, quantity: int}|null the item most taken from the fishing bobber, or from every other object */
    private function topTaken(int $characterId, bool $fishing): ?array
    {
        $row = DB::table('loot_drops as d')->where('d.character_id', $characterId)->where('d.counted', true)->where('d.via', LootVia::Window->value)
            ->where('d.source_kind', LootSourceKind::Object->value)->whereRaw($fishing ? 'd.source_id = ?' : 'd.source_id is distinct from ?', [GameObjects::FISHING_BOBBER])
            ->when(! $fishing, fn ($query) => $query->whereNotExists(fn ($fished) => $fished->from('loot_drops as f')->whereColumn('f.character_id', 'd.character_id')
                ->whereColumn('f.item_id', 'd.item_id')->where('f.counted', true)->where('f.via', LootVia::Window->value)->where('f.source_kind', LootSourceKind::Object->value)
                ->where('f.source_id', GameObjects::FISHING_BOBBER)))
            ->groupBy('d.item_id')
            ->selectRaw('d.item_id, sum(d.quantity)::bigint as quantity, (select n.name from names n where n.kind = ? and n.id = d.item_id order by n.votes desc, n.name limit 1) as name', [NameKind::Item->value])
            ->orderByRaw('sum(d.quantity) desc')->orderBy('d.item_id')->first();

        return $row === null ? null : ['item_id' => (int) $row->item_id, 'name' => (string) ($row->name ?? 'Item '.$row->item_id), 'quantity' => (int) $row->quantity];
    }
}
