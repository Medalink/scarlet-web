<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** The ranked query of each leaderboard in a scope: the one place that says who is on a board and in what order. */
class BoardQuery
{
    private const string BY_TIME = 'cl.before_seconds, c.id';

    private const string BY_LEVEL = 's.level desc, c.played_seconds, c.id';

    private const string BY_QUESTS = 's.quests desc, c.id';

    private const string BY_QUESTS_COMPLETED = 'c.quests_completed desc, c.id';

    private const string BY_KILLS = 's.kills desc, c.id';

    private const string BY_JUMPS = 's.jumps desc, c.id';

    private const string BY_RUNS = 's.runs desc, c.id';

    private const string BY_DISTANCE = 's.distance desc, c.id';

    private const string BY_CAMP = 'k.seconds desc, c.id';

    private const string BY_GATHERED = 'g.looted desc, c.id';

    private const string BY_CRAFTED = 'g.crafts desc, c.id';

    private const string BY_FEAT_SCORE = 'c.feat_score desc, c.id';

    /** The query of one board, public characters only (a player's own page: the player's characters), best first. A row carries `name`, `race`, `class`, `account_id`, `whole_life`, `first_seen_at`, `deaths`, `ruleset_id`, `value` and `at`. */
    public function ranked(Board $board, PageScope $scope): Builder
    {
        $query = $board->level() === null ? $this->totals($board, $scope) : $this->fastest($board, $scope);

        return $query->orderByRaw($this->order($board));
    }

    /** The query of one board with each row's `place` in the board's order, `total` rows on the board and the character's id as `character_id`. */
    public function placed(Board $board, PageScope $scope): Builder
    {
        return $this->ranked($board, $scope)->reorder()
            ->addSelect('c.id as character_id')
            ->selectRaw($this->place($board));
    }

    /** A board value as the page and the API print it: a whole number stays whole, a decimal keeps three places. */
    public function value(mixed $value): int|float
    {
        $number = $value + 0;

        return is_float($number) ? round($number, 3) : $number;
    }

    /**
     * What decides a place on a board, best first, with the character id breaking every tie.
     *
     * @return literal-string
     */
    private function order(Board $board): string
    {
        return match ($board) {
            Board::FastestTo60, Board::FastestTo40, Board::FastestTo20 => self::BY_TIME,
            Board::HighestWithoutDying => self::BY_LEVEL,
            Board::MostQuests => self::BY_QUESTS,
            Board::MostQuestsCompleted => self::BY_QUESTS_COMPLETED,
            Board::MostKills => self::BY_KILLS,
            Board::MostJumps => self::BY_JUMPS,
            Board::MostDungeonRuns => self::BY_RUNS,
            Board::MostDistance => self::BY_DISTANCE,
            Board::MostTimeByTheFire => self::BY_CAMP,
            Board::MostFishCaught, Board::MostHerbsGathered, Board::MostOreMined, Board::MostCreaturesSkinned => self::BY_GATHERED,
            Board::MostAlchemyCrafts, Board::MostBlacksmithingCrafts, Board::MostEnchantingCrafts, Board::MostEngineeringCrafts,
            Board::MostLeatherworkingCrafts, Board::MostTailoringCrafts, Board::MostCookingCrafts, Board::MostFirstAidCrafts => self::BY_CRAFTED,
            Board::FeatScore => self::BY_FEAT_SCORE,
        };
    }

    /**
     * The place and the size of a board, counted in the order of `order()`.
     *
     * @return literal-string
     */
    private function place(Board $board): string
    {
        return match ($board) {
            Board::FastestTo60, Board::FastestTo40, Board::FastestTo20 => 'row_number() over (order by '.self::BY_TIME.') as place, count(*) over () as total',
            Board::HighestWithoutDying => 'row_number() over (order by '.self::BY_LEVEL.') as place, count(*) over () as total',
            Board::MostQuests => 'row_number() over (order by '.self::BY_QUESTS.') as place, count(*) over () as total',
            Board::MostQuestsCompleted => 'row_number() over (order by '.self::BY_QUESTS_COMPLETED.') as place, count(*) over () as total',
            Board::MostKills => 'row_number() over (order by '.self::BY_KILLS.') as place, count(*) over () as total',
            Board::MostJumps => 'row_number() over (order by '.self::BY_JUMPS.') as place, count(*) over () as total',
            Board::MostDungeonRuns => 'row_number() over (order by '.self::BY_RUNS.') as place, count(*) over () as total',
            Board::MostDistance => 'row_number() over (order by '.self::BY_DISTANCE.') as place, count(*) over () as total',
            Board::MostTimeByTheFire => 'row_number() over (order by '.self::BY_CAMP.') as place, count(*) over () as total',
            Board::MostFishCaught, Board::MostHerbsGathered, Board::MostOreMined, Board::MostCreaturesSkinned => 'row_number() over (order by '.self::BY_GATHERED.') as place, count(*) over () as total',
            Board::MostAlchemyCrafts, Board::MostBlacksmithingCrafts, Board::MostEnchantingCrafts, Board::MostEngineeringCrafts,
            Board::MostLeatherworkingCrafts, Board::MostTailoringCrafts, Board::MostCookingCrafts, Board::MostFirstAidCrafts => 'row_number() over (order by '.self::BY_CRAFTED.') as place, count(*) over () as total',
            Board::FeatScore => 'row_number() over (order by '.self::BY_FEAT_SCORE.') as place, count(*) over () as total',
        };
    }

    /**
     * Time to reach a level, as the client's played total when Scarlet saw the level-up into it, for public characters.
     * A character first seen at or above the level has no row for it, so it is not listed.
     */
    private function fastest(Board $board, PageScope $scope): Builder
    {
        $level = (int) $board->level();
        $totals = $this->sessionTotals($scope);

        return DB::table('character_levels as cl')
            ->join('characters as c', 'c.id', '=', 'cl.character_id')
            ->joinSub($totals, 's', 's.character_id', '=', 'c.id')
            ->tap(fn (Builder $q): Builder => $this->listed($q, $scope))
            ->where('cl.counted', true)
            ->where('cl.level', $level)
            ->where('cl.xp_off_reach', false)
            ->when(is_int($scope->ids), fn (Builder $q): Builder => $q->where('cl.ruleset_id', $scope->ids))
            ->when(is_array($scope->ids), fn (Builder $q): Builder => $q->whereIn('cl.ruleset_id', $scope->ids))
            ->select('c.name', 'c.race', 'c.class', 'c.account_id', 'c.whole_life', 'c.first_seen_at', 's.deaths', 's.ruleset_id', 'cl.before_seconds as value', 'cl.at');
    }

    /** The boards that add up sessions. */
    private function totals(Board $board, PageScope $scope): Builder
    {
        $query = DB::table('characters as c')
            ->joinSub($this->sessionTotals($scope), 's', 's.character_id', '=', 'c.id')
            ->tap(fn (Builder $q): Builder => $this->listed($q, $scope))
            ->select('c.name', 'c.race', 'c.class', 'c.account_id', 'c.whole_life', 'c.first_seen_at', 's.deaths', 's.ruleset_id', 's.last_at as at');

        return match ($board) {
            Board::HighestWithoutDying => $query->where('c.whole_life', true)->where('s.deaths', 0)->where('s.deaths_known', true)->whereNotNull('s.level')->addSelect('s.level as value'),
            Board::MostQuests => $query->where('s.quests', '>', 0)->addSelect('s.quests as value'),
            Board::MostQuestsCompleted => $query->where('c.quests_completed', '>', 0)->addSelect('c.quests_completed as value'),
            Board::MostKills => $query->where('s.kills', '>', 0)->addSelect('s.kills as value'),
            Board::MostJumps => $query->where('s.jumps', '>', 0)->addSelect('s.jumps as value'),
            Board::FeatScore => $query->where('c.feat_score', '>', 0)->addSelect('c.feat_score as value'),
            Board::MostDungeonRuns => $query->where('s.runs', '>', 0)->addSelect('s.runs as value'),
            Board::MostTimeByTheFire => $query->joinSub($this->campTime($scope), 'k', 'k.character_id', '=', 'c.id')->where('k.seconds', '>', 0)->addSelect('k.seconds as value'),
            Board::MostFishCaught, Board::MostHerbsGathered, Board::MostOreMined, Board::MostCreaturesSkinned => $query->joinSub($this->gathered($board, $scope), 'g', 'g.character_id', '=', 'c.id')->where('g.looted', '>', 0)->addSelect('g.looted as value'),
            Board::MostAlchemyCrafts, Board::MostBlacksmithingCrafts, Board::MostEnchantingCrafts, Board::MostEngineeringCrafts,
            Board::MostLeatherworkingCrafts, Board::MostTailoringCrafts, Board::MostCookingCrafts, Board::MostFirstAidCrafts => $query->joinSub($this->crafted($board, $scope), 'g', 'g.character_id', '=', 'c.id')->where('g.crafts', '>', 0)->addSelect('g.crafts as value'),
            default => $query->where('s.distance', '>', 0)->addSelect('s.distance as value'),
        };
    }

    /** Who is on a board: the public characters, or for a player's own page the player's characters, private ones included. */
    private function listed(Builder $query, PageScope $scope): Builder
    {
        return $scope->characters === null ? $query->where('c.is_public', true) : $query->whereIn('c.id', $scope->characters);
    }

    /** Narrows a by-character table to the characters of a player's own page. */
    private function ofCharacters(Builder $query, PageScope $scope): Builder
    {
        return $scope->characters === null ? $query : $query->whereIn('character_id', $scope->characters);
    }

    /** Time by a campfire of each character in the rulesets of the scope, from the by-character aggregate. */
    private function campTime(PageScope $scope): Builder
    {
        return DB::table(Rollup::CampStaysDailyCharacter->value)
            ->tap(fn (Builder $q): Builder => $this->ofCharacters($q, $scope))
            ->when(is_int($scope->ids), fn (Builder $q): Builder => $q->where('ruleset_id', $scope->ids))
            ->when(is_array($scope->ids), fn (Builder $q): Builder => $q->whereIn('ruleset_id', $scope->ids))
            ->groupBy('character_id')
            ->selectRaw('character_id, sum(seconds) as seconds');
    }

    /** Looted attempts of each character in the rulesets of the scope, of the gathering the board counts, from the by-character aggregate. */
    private function gathered(Board $board, PageScope $scope): Builder
    {
        return DB::table(Rollup::GathersDailyCharacter->value)
            ->tap(fn (Builder $q): Builder => $this->ofCharacters($q, $scope))
            ->where('kind', $board->gathering()?->value)
            ->when(is_int($scope->ids), fn (Builder $q): Builder => $q->where('ruleset_id', $scope->ids))
            ->when(is_array($scope->ids), fn (Builder $q): Builder => $q->whereIn('ruleset_id', $scope->ids))
            ->groupBy('character_id')
            ->selectRaw('character_id, sum(looted) as looted');
    }

    /** Crafts of each character in the rulesets of the scope, of the profession the board counts, from the by-character aggregate. */
    private function crafted(Board $board, PageScope $scope): Builder
    {
        return DB::table(Rollup::CraftsDailyCharacter->value)
            ->tap(fn (Builder $q): Builder => $this->ofCharacters($q, $scope))
            ->where('profession_id', $board->crafting()?->skillLine())
            ->when(is_int($scope->ids), fn (Builder $q): Builder => $q->where('ruleset_id', $scope->ids))
            ->when(is_array($scope->ids), fn (Builder $q): Builder => $q->whereIn('ruleset_id', $scope->ids))
            ->groupBy('character_id')
            ->selectRaw('character_id, sum(crafts) as crafts');
    }

    /** Dungeon runs of the character: a run that carries on the one before it is the same run. */
    private function runs(PageScope $scope): Builder
    {
        return DB::table('dungeon_runs as d')
            ->whereColumn('d.character_id', 'sessions.character_id')
            ->where('d.counted', true)
            ->where('d.continues_previous', false)
            ->when(is_int($scope->ids), fn (Builder $q): Builder => $q->where('d.ruleset_id', $scope->ids))
            ->when(is_array($scope->ids), fn (Builder $q): Builder => $q->whereIn('d.ruleset_id', $scope->ids))
            ->selectRaw('count(*)');
    }

    /** Jumps of the character, counted from its first recorded session. */
    private function jumps(PageScope $scope): Builder
    {
        return DB::table('movement_stretches as m')
            ->whereColumn('m.character_id', 'sessions.character_id')
            ->where('m.counted', true)
            ->when(is_int($scope->ids), fn (Builder $q): Builder => $q->where('m.ruleset_id', $scope->ids))
            ->when(is_array($scope->ids), fn (Builder $q): Builder => $q->whereIn('m.ruleset_id', $scope->ids))
            ->selectRaw('coalesce(sum(jumps), 0)');
    }

    /** What a character's counted sessions add up to, one row per character, for the rulesets of the scope. */
    private function sessionTotals(PageScope $scope): Builder
    {
        return DB::table('sessions')
            ->tap(fn (Builder $q): Builder => $this->ofCharacters($q, $scope))
            ->where('counted', true)
            ->when(is_int($scope->ids), fn (Builder $q): Builder => $q->where('ruleset_id', $scope->ids))
            ->when(is_array($scope->ids), fn (Builder $q): Builder => $q->whereIn('ruleset_id', $scope->ids))
            ->groupBy('character_id')
            ->selectRaw('character_id, coalesce(sum(deaths), 0) as deaths, coalesce(sum(quests_done), 0) as quests, coalesce(sum(kills), 0) as kills')
            ->selectRaw('bool_and(deaths is not null) as deaths_known')
            ->selectSub($this->runs($scope), 'runs')
            ->selectSub($this->jumps($scope), 'jumps')
            ->selectRaw('coalesce(sum(distance), 0) as distance, max(level_end) as level, max(end_at) as last_at')
            ->selectRaw('(array_agg(ruleset_id order by end_at desc))[1] as ruleset_id');
    }
}
