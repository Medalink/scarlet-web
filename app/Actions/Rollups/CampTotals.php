<?php

namespace App\Actions\Rollups;

use App\Support\CharacterScope;
use App\Support\Rollup;
use App\Support\RollupRead;

class CampTotals
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * What the stays by a campfire add up to in the scope. A visit is a stay that began in the session; `seconds` and
     * the AFK seconds count every stay, a carried one included. The median is of the visits. Null while fewer than
     * the scope's threshold of characters stayed.
     *
     * @param  int|list<int>|CharacterScope|null  $scope
     * @return array{visits: int, characters: int, seconds: int, median_seconds: float|null, earned: int, sat: int, tented: int, tent_rests: int, logged_out: int, died_after: int, afk_seconds: int, afk_known_seconds: int, crafted: int, crafts_known: int, placed_tent: int, placed_chair: int}|null
     */
    public function handle(int|array|CharacterScope|null $scope = null): ?array
    {
        $query = $this->read->from(Rollup::CampStaysHourlyRuleset, $scope)->selectRaw(
            'coalesce(sum(visits), 0) as visits, distinct_count(rollup(characters)) as characters, coalesce(sum(seconds), 0) as seconds,'
            .' approx_percentile(0.5, rollup(visit_seconds)) as median_seconds,'
            .' coalesce(sum(earned), 0) as earned, coalesce(sum(sat), 0) as sat, coalesce(sum(tented), 0) as tented,'
            .' coalesce(sum(tent_rests), 0) as tent_rests, coalesce(sum(logged_out), 0) as logged_out, coalesce(sum(died_after), 0) as died_after,'
            .' coalesce(sum(afk_seconds), 0) as afk_seconds, coalesce(sum(afk_known_seconds), 0) as afk_known_seconds,'
            .' coalesce(sum(crafted), 0) as crafted, coalesce(sum(crafts_known), 0) as crafts_known,'
            .' coalesce(sum(placed_tent), 0) as placed_tent, coalesce(sum(placed_chair), 0) as placed_chair'
        );
        $row = $this->read->rows($query)[0] ?? null;
        if ($row === null || (int) $row['characters'] < $this->read->threshold($scope)) {
            return null;
        }

        return [
            'visits' => (int) $row['visits'],
            'characters' => (int) $row['characters'],
            'seconds' => (int) $row['seconds'],
            'median_seconds' => $row['median_seconds'] === null ? null : (float) $row['median_seconds'],
            'earned' => (int) $row['earned'],
            'sat' => (int) $row['sat'],
            'tented' => (int) $row['tented'],
            'tent_rests' => (int) $row['tent_rests'],
            'logged_out' => (int) $row['logged_out'],
            'died_after' => (int) $row['died_after'],
            'afk_seconds' => (int) $row['afk_seconds'],
            'afk_known_seconds' => (int) $row['afk_known_seconds'],
            'crafted' => (int) $row['crafted'],
            'crafts_known' => (int) $row['crafts_known'],
            'placed_tent' => (int) $row['placed_tent'],
            'placed_chair' => (int) $row['placed_chair'],
        ];
    }
}
