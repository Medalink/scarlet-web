<?php

namespace App\Actions\Ingest;

use Illuminate\Support\Facades\DB;

class SummarizeBaselines
{
    /**
     * Copy the totals of the latest lifetime reads onto the character: quests completed, titles known and zones
     * explored. A character with no read of a kind gets a null for it.
     */
    public function handle(int $characterId): void
    {
        DB::update(
            <<<'SQL'
            update characters set
                quests_completed = (select b.total from quest_baselines b where b.character_id = characters.id order by b.at desc limit 1),
                titles_known = (select b.total from title_baselines b where b.character_id = characters.id order by b.at desc limit 1),
                zones_explored = (select b.zones from explored_baselines b where b.character_id = characters.id order by b.at desc limit 1)
            where id = ?
            SQL,
            [$characterId],
        );
    }
}
