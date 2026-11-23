<?php

namespace App\Actions\Sessions;

use App\Actions\Rollups\RebuildSessionRates;
use App\Models\Character;
use App\Support\Recap\Counters;
use App\Support\Recap\History;
use App\Support\Recap\Peers;
use App\Support\Recap\Route;
use App\Support\Recap\Scope;
use App\Support\Recap\Stretches;
use App\Support\Recap\Timeline;
use App\Support\Recap\Top;
use App\Support\SessionTitle;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * The SessionRecap of one session of one character (CONTRACTS.md, "Session recap"), built from the session row and the fact tables the way the app's recap.rs builds it
 * from the log. `buckets.combat` is zero until the decoder keeps fight times with a time, `feats` is empty until feat events exist, `compare` is null while the bracket
 * has fewer than 20 sessions, and `links` is left to ShowSession. `$economy` false leaves out what a public page may not show without the owner's say.
 */
class BuildRecap
{
    private const array ECONOMY_COUNTERS = ['earned', 'spent', 'sold', 'repairs'];

    /** @return array<string, mixed>|null null when the character has no session that starts at `$start` */
    public function handle(Character $character, int $start, bool $economy): ?array
    {
        $session = DB::table('sessions')->where('character_id', $character->id)->where('session_start', $start)->first();
        if ($session === null) {
            return null;
        }
        $scope = new Scope($character->id, $start);
        $counters = Counters::read($scope, $session);
        $played = intdiv((int) $session->played_ms, 1000);
        $level = [(int) $session->level_start, max((int) $session->level_start, (int) $session->level_end)];
        $stretches = Stretches::read($scope);
        $miles = (($counters['foot'] ?? 0) + ($counters['walk'] ?? 0) + ($counters['mount'] ?? 0) + ($counters['swim'] ?? 0)) / 1760;
        $history = History::read($scope, History::totals([
            'played' => $played,
            'xp' => $counters['xp'] ?? 0,
            'kills' => $counters['kills'] ?? 0,
            'quests' => $counters['quests'] ?? 0,
            'earned' => $counters['earned'] ?? 0,
            'distance' => (int) round((float) $session->distance),
            'deaths' => $counters['deaths'] ?? 0,
        ], $miles * 1760));
        $levels = Timeline::levels($scope);

        $recap = [
            'v' => 1,
            'character' => $character->name,
            'class' => (string) $session->class,
            'start' => $start,
            'end' => $session->end_at === null ? $start + $played : (int) strtotime((string) $session->end_at),
            'played' => $played,
            'level' => $level,
            'zone' => $stretches['zone'],
            'title' => SessionTitle::make($level, $stretches['zone'], $stretches['dungeon'], $counters['quests'] ?? 0, $counters['kills'] ?? 0, Counters::gathered($scope), $miles, count(array_unique(array_column($stretches['zones'], 'name'))), $played),
            'xp' => Counters::xp($scope),
            'runs' => (int) $session->dungeon_runs,
            'counters' => $counters,
            'buckets' => Timeline::buckets($scope, (int) $session->played_ms),
            'zones' => $stretches['zones'],
            'levels' => $levels,
            'moments' => Timeline::moments($scope),
            'route' => Route::read($scope),
            'feats' => ['earned' => [], 'near' => []],
            'bests' => $history['bests'],
            'own' => $history['own'],
            'top' => Top::read($scope),
            'compare' => $this->compare($session, $counters, $played),
        ];

        return $economy ? $recap : $this->withoutEconomy($recap);
    }

    /**
     * @param  array<string, int>  $counters
     * @return array<string, mixed>|null null for a session under ten minutes, which the bands leave out, and when its bracket has no band
     */
    private function compare(stdClass $session, array $counters, int $played): ?array
    {
        if ($played * 1000 < RebuildSessionRates::MIN_PLAYED_MS || $session->level_start === null) {
            return null;
        }
        $hours = $played / 3600;

        return Peers::read((int) $session->ruleset_id, (int) $session->level_start, [
            'xp_h' => ($counters['xp'] ?? 0) / $hours,
            'kills_h' => ($counters['kills'] ?? 0) / $hours,
            'quests_h' => ($counters['quests'] ?? 0) / $hours,
        ]);
    }

    /**
     * @param  array<string, mixed>  $recap
     * @return array<string, mixed>
     */
    private function withoutEconomy(array $recap): array
    {
        $recap['counters'] = array_diff_key($recap['counters'], array_flip(self::ECONOMY_COUNTERS));
        $recap['bests'] = array_values(array_diff($recap['bests'], ['earned']));

        return $recap;
    }
}
