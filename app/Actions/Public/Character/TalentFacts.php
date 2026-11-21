<?php

namespace App\Actions\Public\Character;

use App\Support\TalentKind;
use App\Support\TalentRanks;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class TalentFacts
{
    /** Points in one tree that make the build deep enough to count for `first_deep`. */
    public const int DEEP_POINTS = 31;

    /** A pick refunded within this many minutes was a pick taken back. */
    public const int TAKEN_BACK_MINUTES = 10;

    /** The level of the first talent point. */
    public const int FIRST_POINT_LEVEL = 10;

    /**
     * Counts about one character's talents, each with the n it comes from.
     *
     * - `sharing`: of the other characters whose latest build was read at the same level in the same ruleset, how many hold exactly this build.
     * - `least`: the talent of the latest build that the fewest characters of the same class and spec hold.
     * - `first_deep`: the level of the first build with the deep number of points in one tree.
     * - `unspent`: how long the points the addon saw being picked waited after the level-up that gave them.
     * - `taken_back`: picks refunded within the minutes.
     *
     * @param  list<\stdClass>  $snapshots  the character's builds, oldest first, at least one
     * @return array{sharing: array{same: int, total: int, level: int}|null, least: array{node: int, spell: int|null, taken: int, total: int}|null, first_deep: array{level: int|null, first_read: bool}|null, unspent: array{points: int, longest_s: float, median_s: float}|null, taken_back: array{refunded: int, picks: int}}
     */
    public function handle(int $characterId, array $snapshots): array
    {
        $last = $snapshots[count($snapshots) - 1];

        return [
            'sharing' => $this->sharing($characterId, $last),
            'least' => $this->least($characterId, $last),
            'first_deep' => $this->firstDeep($snapshots),
            'unspent' => $this->unspent($characterId),
            'taken_back' => $this->takenBack($characterId),
        ];
    }

    /** The latest counted build of each character of a ruleset other than this one. */
    private function latestOthers(int $characterId, int $ruleset): Builder
    {
        return DB::table('talent_snapshots')->where('counted', true)->where('ruleset_id', $ruleset)->where('character_id', '<>', $characterId)
            ->selectRaw('distinct on (character_id) character_id, level, build_hash, spec_tab_id, ranks')->orderBy('character_id')->orderByDesc('at');
    }

    /** @return array{same: int, total: int, level: int}|null */
    private function sharing(int $characterId, \stdClass $last): ?array
    {
        if ($last->level === null) {
            return null;
        }
        $row = DB::query()->fromSub($this->latestOthers($characterId, (int) $last->ruleset_id), 'l')->where('l.level', $last->level)
            ->selectRaw('count(*) as total, count(*) filter (where l.build_hash = ?) as same', [$last->build_hash])->first();

        return ['same' => (int) ($row->same ?? 0), 'total' => (int) ($row->total ?? 0), 'level' => (int) $last->level];
    }

    /** @return array{node: int, spell: int|null, taken: int, total: int}|null */
    private function least(int $characterId, \stdClass $last): ?array
    {
        $held = TalentRanks::parse((string) $last->ranks);
        $class = DB::table('characters')->where('id', $characterId)->value('class');
        if ($held === [] || $last->spec_tab_id === null || ! is_string($class)) {
            return null;
        }
        $rows = DB::query()->fromSub($this->latestOthers($characterId, (int) $last->ruleset_id), 'l')->join('characters as c', 'c.id', '=', 'l.character_id')
            ->where('c.class', $class)->where('l.spec_tab_id', $last->spec_tab_id)->pluck('l.ranks');
        if ($rows->isEmpty()) {
            return null;
        }
        $taken = [];
        foreach ($rows as $ranks) {
            foreach (array_keys(TalentRanks::parse((string) $ranks)) as $node) {
                $taken[$node] = ($taken[$node] ?? 0) + 1;
            }
        }
        $least = null;
        foreach ($held as $node => $rank) {
            $count = $taken[$node] ?? 0;
            if ($least === null || $count < $least['taken']) {
                $least = ['node' => $node, 'spell' => $rank['spell'], 'taken' => $count, 'total' => count($rows)];
            }
        }

        return $least;
    }

    /**
     * @param  list<\stdClass>  $snapshots
     * @return array{level: int|null, first_read: bool}|null
     */
    private function firstDeep(array $snapshots): ?array
    {
        foreach ($snapshots as $index => $row) {
            foreach (TalentRanks::tabs((string) $row->tab_points) as $tab) {
                if ($tab['spent'] >= self::DEEP_POINTS) {
                    return ['level' => $row->level === null ? null : (int) $row->level, 'first_read' => $index === 0];
                }
            }
        }

        return null;
    }

    /** @return array{points: int, longest_s: float, median_s: float}|null */
    private function unspent(int $characterId): ?array
    {
        $waits = DB::table('talent_changes as t')
            ->join('level_ups as e', fn ($join) => $join->on('e.character_id', '=', 't.character_id')->whereRaw('e.reached = t.point_no + ?', [self::FIRST_POINT_LEVEL - 1]))
            ->where('t.character_id', $characterId)->where('t.counted', true)->where('e.counted', true)
            ->where('t.kind', TalentKind::Pick->value)->whereNull('t.respec_index')->whereNotNull('t.point_no')
            ->selectRaw('extract(epoch from (t.at - e.at)) as waited')->pluck('waited')
            ->map(fn (mixed $seconds): float => (float) $seconds)->filter(fn (float $seconds): bool => $seconds >= 0)->sort()->values()->all();
        if ($waits === []) {
            return null;
        }
        $middle = intdiv(count($waits), 2);

        return [
            'points' => count($waits),
            'longest_s' => $waits[count($waits) - 1],
            'median_s' => count($waits) % 2 === 1 ? $waits[$middle] : ($waits[$middle - 1] + $waits[$middle]) / 2,
        ];
    }

    /** @return array{refunded: int, picks: int} */
    private function takenBack(int $characterId): array
    {
        $refunded = DB::table('talent_changes as r')->where('r.character_id', $characterId)->where('r.counted', true)->where('r.kind', TalentKind::Refund->value)
            ->whereExists(fn ($pick) => $pick->from('talent_changes as p')->whereColumn('p.character_id', 'r.character_id')->whereColumn('p.node_id', 'r.node_id')
                ->where('p.kind', TalentKind::Pick->value)->whereNull('p.respec_index')->whereColumn('p.at', '<', 'r.at')
                ->whereRaw('p.at >= r.at - make_interval(mins => ?)', [self::TAKEN_BACK_MINUTES]))
            ->count();
        $picks = DB::table('talent_changes')->where('character_id', $characterId)->where('counted', true)->where('kind', TalentKind::Pick->value)->whereNull('respec_index')->count();

        return ['refunded' => $refunded, 'picks' => $picks];
    }
}
