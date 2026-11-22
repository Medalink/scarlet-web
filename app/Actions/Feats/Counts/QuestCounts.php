<?php

namespace App\Actions\Feats\Counts;

use App\Actions\Public\QuestRecord;
use App\Models\Character;
use App\Support\Feat;
use App\Support\XpSource;
use Illuminate\Support\Facades\DB;

/** The quest feats: turn-ins, abandons and the zones of both. */
class QuestCounts
{
    private const int LOCAL_HERO_ZONE_QUESTS = 25;

    /**
     * One count per quest feat, keyed by the enum value; null when the feat does not apply to the character.
     *
     * @return array<string, int|float|null>
     */
    public function handle(Character $character): array
    {
        $id = (int) $character->id;
        $sessions = DB::table('sessions')->where('character_id', $id)->where('counted', true)
            ->selectRaw('coalesce(sum(quests_done), 0) as quests, coalesce(max(quests_done), 0) as errand')->first();
        $zones = DB::table('quest_events')->where('character_id', $id)->where('counted', true)->where('event', QuestRecord::TURNED_IN)
            ->whereNotNull('zone_id')->groupBy('zone_id')->selectRaw('zone_id, count(*) as turned')->pluck('turned', 'zone_id');

        return [
            Feat::Questing->value => (int) ($sessions->quests ?? 0),
            Feat::ErrandRunner->value => (int) ($sessions->errand ?? 0),
            Feat::Fickle->value => DB::table('quest_events')->where('character_id', $id)->where('counted', true)->where('event', QuestRecord::ABANDONED)->count(),
            Feat::Cartographer->value => DB::table('xp_gains')->where('character_id', $id)->where('counted', true)->where('source', XpSource::Exploring->value)->count(),
            Feat::LocalHero->value => $zones->filter(fn (mixed $turned): bool => (int) $turned >= self::LOCAL_HERO_ZONE_QUESTS)->count(),
            Feat::Thorough->value => (int) $zones->max(),
        ];
    }
}
