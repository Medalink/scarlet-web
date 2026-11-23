<?php

namespace App\Actions\Feats\Counts;

use App\Models\Character;
use App\Support\Feat;
use Illuminate\Support\Facades\DB;

/** The time feats: level, play time, play days, session counts and the run without a dying level. */
class TimeCounts
{
    private const int LONG_SESSION_MS = 7_200_000;

    private const int DAY_SECONDS = 86_400;

    /**
     * One count per time feat, keyed by the enum value; null when the feat does not apply to the character.
     *
     * @return array<string, int|float|null>
     */
    public function handle(Character $character): array
    {
        $seconds = $character->played_known ? $character->played_seconds : $character->recorded_seconds;
        $level = $character->level === null ? null : (int) $character->level;
        $sessions = DB::table('sessions')->where('character_id', $character->id)->where('counted', true)
            ->selectRaw('count(*) filter (where played_ms >= ?) as long', [self::LONG_SESSION_MS])->first();
        $days = $this->days((int) $character->id);

        return [
            Feat::Summit->value => $level,
            Feat::Veteran->value => (int) floor((int) $seconds / 3600),
            Feat::Devoted->value => count($days),
            Feat::Steadfast->value => $this->longestRun($days),
            Feat::Seasoned->value => $this->weeks($days),
            Feat::LongHaul->value => (int) ($sessions->long ?? 0),
            Feat::Unbroken->value => $character->whole_life ? $this->unbroken((int) $character->id, $level) : null,
        ];
    }

    /** @return array<int, string> the UTC days with play, ascending */
    private function days(int $characterId): array
    {
        return DB::table('play_spans')->where('character_id', $characterId)->where('counted', true)->where('ms', '>', 0)
            ->selectRaw("(at at time zone 'UTC')::date as day")->distinct()->orderBy('day')->get()
            ->map(fn (object $row): string => (string) $row->day)->values()->all();
    }

    /** @param  array<int, string>  $days */
    private function longestRun(array $days): int
    {
        $best = 0;
        $run = 0;
        $previous = null;
        foreach ($days as $day) {
            $time = self::day($day)->getTimestamp();
            $run = $previous !== null && $time - $previous === self::DAY_SECONDS ? $run + 1 : 1;
            $best = max($best, $run);
            $previous = $time;
        }

        return $best;
    }

    /** @param  array<int, string>  $days */
    private function weeks(array $days): int
    {
        $weeks = [];
        foreach ($days as $day) {
            $weeks[self::day($day)->format('o-W')] = true;
        }

        return count($weeks);
    }

    /** The level the character died at first, or its level when it never died; null when the first death has no level. */
    private function unbroken(int $characterId, ?int $level): ?int
    {
        $death = DB::table('deaths')->where('character_id', $characterId)->orderBy('at')->first(['level']);
        if ($death === null) {
            return $level === null ? null : (int) $level;
        }

        return $death->level === null ? null : (int) $death->level;
    }

    private static function day(string $day): \DateTimeImmutable
    {
        return new \DateTimeImmutable($day.' 00:00:00 UTC');
    }
}
