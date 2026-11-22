<?php

namespace App\Actions\Feats\Counts;

use App\Models\Character;
use App\Support\CreatureClass;
use App\Support\Feat;
use Illuminate\Support\Facades\DB;

/** The creature feats: the kinds and classes of creature a character has seen. */
class CreatureCounts
{
    /** The `npc_level` the client writes when it hides a creature's level (docs/ROWS.md). */
    private const int HIDDEN_LEVEL = -1;

    /**
     * One count per creature feat, keyed by the enum value; null when the feat does not apply to the character.
     *
     * @return array<string, int|float|null>
     */
    public function handle(Character $character): array
    {
        $sightings = DB::table('creature_sightings')->where('character_id', (int) $character->id)->where('counted', true)
            ->selectRaw('count(distinct npc_id) as naturalist')
            ->selectRaw('count(distinct npc_id) filter (where classification in (?, ?)) as tracker', [CreatureClass::Rare->value, CreatureClass::RareElite->value])
            ->selectRaw('count(distinct npc_id) filter (where classification = ?) as scout', [CreatureClass::Elite->value])
            ->selectRaw('count(distinct npc_id) filter (where classification = ?) as witness', [CreatureClass::WorldBoss->value])
            ->selectRaw('count(distinct npc_id) filter (where npc_level = ?) as skullseer', [self::HIDDEN_LEVEL])->first();

        return [
            Feat::Tracker->value => (int) ($sightings->tracker ?? 0),
            Feat::Scout->value => (int) ($sightings->scout ?? 0),
            Feat::Witness->value => (int) ($sightings->witness ?? 0),
            Feat::Naturalist->value => (int) ($sightings->naturalist ?? 0),
            Feat::Skullseer->value => (int) ($sightings->skullseer ?? 0),
        ];
    }
}
