<?php

namespace App\Support;

use App\Actions\Public\ResolvePlayerName;

/**
 * Killer rows of the public aggregates, made safe to show. Rows imported from before the decoder wrote a killer kind
 * have none, so a name that looks like a player's is hidden whatever its kind says, and a kind-less creature is a
 * creature. Players merge into one row; a visitor never sees which one.
 */
final class KillerRows
{
    /**
     * @param  list<array<string, mixed>>  $rows  kind, killer, deaths, characters, npc_id
     * @return list<array<string, mixed>>
     */
    public static function shown(array $rows): array
    {
        $merged = [];
        foreach ($rows as $row) {
            $player = $row['kind'] === KillerKind::Player->value || PlayerNameShape::matches((string) $row['killer']);
            $kind = $player ? KillerKind::Player->value : ($row['kind'] ?? KillerKind::Creature->value);
            $killer = $player ? ResolvePlayerName::UNKNOWN : (string) $row['killer'];
            $key = $kind.'|'.$killer;
            $merged[$key] ??= [...$row, 'kind' => $kind, 'killer' => $killer, 'npc_id' => $player ? null : $row['npc_id'], 'deaths' => 0, 'characters' => 0];
            $merged[$key]['deaths'] += (int) $row['deaths'];
            $merged[$key]['characters'] = max($merged[$key]['characters'], (int) $row['characters']);
        }
        $rows = array_values($merged);
        usort($rows, fn (array $a, array $b): int => ($b['deaths'] <=> $a['deaths']) ?: strcmp($a['killer'], $b['killer']));

        return $rows;
    }
}
