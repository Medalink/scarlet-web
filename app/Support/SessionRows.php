<?php

namespace App\Support;

use App\Models\PlaySession;
use Illuminate\Support\Facades\DB;

class SessionRows
{
    /** The decoder's `sessions` keys that have a column; the rest go to `extra`. */
    private const array COLUMNS = [
        'played_ms', 'lines', 'has_start', 'ended', 'class', 'race', 'faction', 'level_start', 'level_end', 'xp_total', 'kills',
        'deaths', 'quests_done', 'copper_earned', 'copper_spent', 'money_start', 'money_end', 'zones', 'dungeon_runs', 'tp_total_s', 'counter_start_ms', 'counter_end_ms', 'xp_off',
    ];

    /** The keys that are bookkeeping of the row itself, not facts about the session. */
    private const array NOT_EXTRA = ['t', 'session', 'start', 'end', 'groups', 'flags', 'played_s'];

    /**
     * Write the session row from the decoder's summary (docs/ROWS.md, `sessions`).
     *
     * @param  array<string, mixed>  $summary
     * @return array{from: int, until: int} the span of the session in epoch seconds
     */
    public function write(int $characterId, int $sessionStart, int $rulesetId, bool $counted, array $summary): array
    {
        $row = [
            'character_id' => $characterId,
            'session_start' => $sessionStart,
            'ruleset_id' => $rulesetId,
            'start_at' => FactTable::instant((int) $summary['start']),
            'end_at' => FactTable::instant((int) $summary['end']),
            'groups' => json_encode($summary['groups'] ?? [], JSON_THROW_ON_ERROR),
            'flags' => json_encode($summary['flags'] ?? [], JSON_THROW_ON_ERROR),
            'counted' => $counted,
            'extra' => json_encode(array_diff_key($summary, array_flip([...self::COLUMNS, ...self::NOT_EXTRA])), JSON_THROW_ON_ERROR),
            'decoded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        foreach (self::COLUMNS as $column) {
            $row[$column] = $summary[$column] ?? null;
        }
        $row['names_counted'] = true;

        PlaySession::query()->upsert([$row], ['character_id', 'session_start'], array_values(array_diff(array_keys($row), ['character_id', 'session_start', 'created_at'])));

        return ['from' => intdiv((int) $summary['start'], 1000), 'until' => intdiv((int) $summary['end'], 1000) + 1];
    }

    public function delete(int $characterId, int $sessionStart): void
    {
        PlaySession::query()->where('character_id', $characterId)->where('session_start', $sessionStart)->delete();
    }

    public function namesCounted(int $characterId, int $sessionStart): bool
    {
        return (bool) PlaySession::query()->where('character_id', $characterId)->where('session_start', $sessionStart)->value('names_counted');
    }

    /** @return array{from: int, until: int}|null the span of the stored row in epoch seconds */
    public function range(int $characterId, int $sessionStart): ?array
    {
        $row = DB::table('sessions')
            ->where('character_id', $characterId)
            ->where('session_start', $sessionStart)
            ->selectRaw('extract(epoch from start_at) as first, extract(epoch from end_at) as last')
            ->first();

        return $row === null ? null : ['from' => (int) floor((float) $row->first), 'until' => (int) ceil((float) $row->last)];
    }
}
