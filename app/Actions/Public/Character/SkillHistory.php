<?php

namespace App\Actions\Public\Character;

use App\Support\Profession;
use App\Support\SkillKind;
use Illuminate\Support\Facades\DB;

class SkillHistory
{
    /** Rank changes read at most. */
    private const int READ = 20000;

    /**
     * The rank of each profession over the character's played time. Each rank change is placed at the played time of its
     * hour: the played time of the hours before it, plus the part of its own hour that had passed. A profession keeps one
     * point per played minute, the last rank of that minute.
     *
     * @param  list<array{0: int, 1: int}>  $buckets  the hours played, as whole hours since the epoch and seconds, oldest first
     * @return list<array{skill: string, points: list<array{0: int, 1: int}>}> each point the played seconds and the rank, oldest first
     */
    public function handle(int $characterId, array $buckets): array
    {
        if ($buckets === []) {
            return [];
        }
        $rows = DB::table('skill_ups')->where('character_id', $characterId)->where('counted', true)->where('kind', SkillKind::Skill->value)
            ->whereIn('skill', array_column(Profession::cases(), 'value'))->whereNotNull('skill_rank')
            ->selectRaw('skill, skill_rank, extract(epoch from at)::bigint as at')->orderBy('at')->limit(self::READ)->get();
        $before = [];
        $played = 0;
        foreach ($buckets as $i => [, $seconds]) {
            $before[$i] = $played;
            $played += $seconds;
        }
        $minutes = [];
        foreach ($rows as $row) {
            $minute = intdiv($this->played($buckets, $before, (int) $row->at), 60);
            $minutes[(string) $row->skill][$minute] = (int) $row->skill_rank;
        }
        $history = [];
        foreach ($minutes as $skill => $ranks) {
            ksort($ranks);
            $history[] = ['skill' => $skill, 'points' => array_map(fn (int $minute, int $rank): array => [$minute * 60, $rank], array_keys($ranks), array_values($ranks))];
        }

        return $history;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $buckets
     * @param  array<int, int>  $before
     */
    private function played(array $buckets, array $before, int $at): int
    {
        $low = 0;
        $high = count($buckets) - 1;
        $found = -1;
        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);
            if ($buckets[$mid][0] * 3600 <= $at) {
                $found = $mid;
                $low = $mid + 1;

                continue;
            }
            $high = $mid - 1;
        }
        if ($found < 0) {
            return 0;
        }

        return $before[$found] + min($buckets[$found][1], $at - $buckets[$found][0] * 3600);
    }
}
