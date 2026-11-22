<?php

namespace App\Actions\Rollups;

use App\Support\CharacterScope;
use App\Support\Rollup;
use App\Support\RollupRead;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ScopePlay
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * How many characters have any play in the scope and how many seconds they played, in one read. Everyone's come from
     * `play_hourly_ruleset`. A player's own come from the counted `play_spans` of the characters: the aggregate groups
     * every span by hour, level and zone and keeps a sketch of characters for each group, which a sum and a count do not need.
     * Null while fewer than the scope's threshold of characters have played.
     *
     * @param  int|list<int>|CharacterScope|null  $scope
     * @return array{characters: int, seconds: int}|null
     */
    public function handle(int|array|CharacterScope|null $scope = null): ?array
    {
        $row = $this->read->rows($scope instanceof CharacterScope ? $this->own($scope) : $this->everyones($scope))[0] ?? null;
        if ($row === null || (int) $row['characters'] < $this->read->threshold($scope)) {
            return null;
        }

        return ['characters' => (int) $row['characters'], 'seconds' => (int) round((float) $row['ms'] / 1000)];
    }

    /** @param  int|list<int>|null  $scope */
    private function everyones(int|array|null $scope): Builder
    {
        return $this->read->shown(
            $this->read->from(Rollup::PlayHourlyRuleset, $scope)->selectRaw('distinct_count(rollup(characters)) as characters, coalesce(sum(ms), 0) as ms'),
            $scope,
        );
    }

    private function own(CharacterScope $scope): Builder
    {
        $perCharacter = $this->read->table('play_spans', $scope)->where('counted', true)->groupBy('character_id')->selectRaw('character_id, sum(ms) as ms');

        return DB::query()->fromSub($perCharacter, 'per_character')->selectRaw('count(*) as characters, coalesce(sum(ms), 0) as ms');
    }
}
