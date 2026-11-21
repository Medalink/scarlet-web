<?php

namespace App\Actions\Public\Character;

use Illuminate\Support\Facades\DB;

class DungeonRuns
{
    /** Runs the page lists. */
    public const int SHOWN = 15;

    /** Rows read at most: a run is a few rows, and the newest ones are listed. */
    private const int READ = 400;

    /**
     * The counted runs of a character, newest first. A row that continues the run before it in the same instance (the
     * run went on into the next session) is part of that run: its time, bosses and deaths are added to it, and its group size is the largest of its rows.
     *
     * @return list<array{instance_id: int, name: string|null, at: string, seconds: float, bosses: int, deaths: int, group_size: int|null}>
     */
    public function handle(int $characterId): array
    {
        $rows = DB::table('dungeon_runs')->where('character_id', $characterId)->where('counted', true)->orderByDesc('at')->orderByDesc('run_index')->limit(self::READ)
            ->get(['instance_id', 'name', 'at', 'inside_ms', 'bosses', 'deaths', 'group_size', 'continues_previous']);
        $runs = [];
        foreach ($rows->reverse() as $row) {
            $last = array_key_last($runs);
            if ($row->continues_previous && $last !== null && $runs[$last]['instance_id'] === (int) $row->instance_id) {
                $runs[$last]['seconds'] += $row->inside_ms / 1000;
                $runs[$last]['bosses'] += (int) $row->bosses;
                $runs[$last]['deaths'] += (int) $row->deaths;
                $runs[$last]['group_size'] = $row->group_size === null ? $runs[$last]['group_size'] : max($runs[$last]['group_size'] ?? 0, (int) $row->group_size);

                continue;
            }
            $runs[] = [
                'instance_id' => (int) $row->instance_id, 'name' => $row->name, 'at' => (string) $row->at, 'seconds' => $row->inside_ms / 1000,
                'bosses' => (int) $row->bosses, 'deaths' => (int) $row->deaths, 'group_size' => $row->group_size === null ? null : (int) $row->group_size,
            ];
        }

        return array_slice(array_reverse($runs), 0, self::SHOWN);
    }
}
