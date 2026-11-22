<?php

namespace App\Actions\Rollups;

use App\Support\CharacterScope;
use App\Support\Rollup;
use App\Support\RollupRead;

class CampBenefitsBySpell
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * The Camp Benefits aura and the nine benefit buffs, one entry per spell seen: gains in the session (an aura carried
     * over from the session before is not a gain), how many of them were given for two hours, seconds held, buffs a death took, and the characters. A spell fewer
     * than the scope's threshold of characters held is left out, and its gains count zero while fewer than
     * that gained it.
     *
     * @param  int|list<int>|CharacterScope|null  $scope
     * @return array<int, array{gains: int, two_hour_gains: int, seconds: int, lost_to_death: int, characters: int}> spell id => counts
     */
    public function handle(int|array|CharacterScope|null $scope = null): array
    {
        $query = $this->read->from(Rollup::CampBenefitsDailyRuleset, $scope)
            ->selectRaw('spell_id, sum(gains) as gains, sum(two_hour_gains) as two_hour_gains, sum(seconds) as seconds, sum(lost_to_death) as lost_to_death, distinct_count(rollup(characters)) as characters, distinct_count(rollup(gain_characters)) as gain_characters')
            ->groupBy('spell_id');

        $min = $this->read->threshold($scope);
        $spells = [];
        foreach ($this->read->rows($this->read->shown($query, $scope)) as $row) {
            $spells[(int) $row['spell_id']] = [
                'gains' => (int) $row['gain_characters'] >= $min ? (int) $row['gains'] : 0,
                'two_hour_gains' => (int) $row['gain_characters'] >= $min ? (int) $row['two_hour_gains'] : 0,
                'seconds' => (int) $row['seconds'],
                'lost_to_death' => (int) $row['lost_to_death'],
                'characters' => (int) $row['characters'],
            ];
        }

        return $spells;
    }
}
