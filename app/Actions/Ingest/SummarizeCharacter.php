<?php

namespace App\Actions\Ingest;

use App\Models\Character;
use Illuminate\Support\Facades\DB;

class SummarizeCharacter
{
    /**
     * Refresh the character's summary columns from its decoded sessions: class, race, faction and level of the
     * latest ones, time played, the span it was seen, and the plausibility state (flagged while any session is).
     */
    public function handle(int $characterId): void
    {
        DB::update(
            <<<'SQL'
            update characters set
                class = (select s.class from sessions s where s.character_id = characters.id and s.class is not null order by s.session_start desc limit 1),
                race = (select s.race from sessions s where s.character_id = characters.id and s.race is not null order by s.session_start desc limit 1),
                faction = (select s.faction from sessions s where s.character_id = characters.id and s.faction is not null order by s.session_start desc limit 1),
                level = (select s.level_end from sessions s where s.character_id = characters.id and s.level_end is not null order by s.session_start desc limit 1),
                sessions_decoded = (select count(*) from sessions s where s.character_id = characters.id),
                played_seconds = (select coalesce(sum(s.played_ms), 0) / 1000 from sessions s where s.character_id = characters.id),
                first_seen_at = (select min(s.start_at) from sessions s where s.character_id = characters.id),
                last_seen_at = (select max(s.end_at) from sessions s where s.character_id = characters.id),
                plausibility = case when exists (select 1 from sessions s where s.character_id = characters.id and not s.counted) then ? else ? end,
                plausibility_flags = (
                    select coalesce(jsonb_agg(distinct flag), '[]'::jsonb)
                    from sessions s, jsonb_array_elements_text(s.flags) as flag
                    where s.character_id = characters.id
                ),
                updated_at = now()
            where id = ?
            SQL,
            [Character::FLAGGED, Character::PLAUSIBLE, $characterId],
        );
    }
}
