<?php

namespace App\View\Character;

use App\Actions\Sessions\BuildRecap;
use App\Actions\Sessions\ListCharacterSessions;
use App\Http\Requests\Web\CharacterRequest;
use App\Models\Character;
use App\View\DateForm;
use App\View\Dates;
use App\View\Format;
use Illuminate\Support\Carbon;

/** The `#sessions` section of /c/{name}: the latest session as a card, the sessions before it as rows, and the days played in the last 16 weeks. */
final class SessionsSection
{
    public const int FIRST = 5;

    public const int MORE = 20;

    private const int WEEKS = 16;

    /** Hours played in a day at which its square takes the second, third, fourth and fifth step. */
    private const array STEPS = [0.0, 1.0, 2.5, 4.0];

    public function __construct(private readonly ListCharacterSessions $list, private readonly BuildRecap $recap) {}

    /**
     * @param  array<string, int>  $days  the seconds played on each UTC day, `Y-m-d`
     * @return array<string, mixed>|null null when the session pages are not public to this viewer, or nothing was recorded
     */
    public function handle(Character $character, ?int $viewer, bool $economy, int $limit, array $days): ?array
    {
        if (! $character->sessionsVisibleTo($viewer)) {
            return null;
        }
        $page = $this->list->handle($character, null, $limit);
        if ($page['sessions'] === []) {
            return null;
        }
        $base = '/c/'.rawurlencode($character->name);
        $latest = $page['sessions'][0];
        $recap = $this->recap->handle($character, $latest['start'], $economy);
        $total = (int) $character->sessions_decoded;

        return [
            'latest' => $recap === null ? null : ['recap' => $recap, 'href' => $base.'/sessions/'.$latest['start']],
            'rows' => array_map(fn (array $row): array => self::row($row, $base), array_slice($page['sessions'], 1)),
            'more' => $page['next'] === null || $limit >= CharacterRequest::MAX_HISTORY ? null : $base.'?sessions='.min($limit + self::MORE, CharacterRequest::MAX_HISTORY).'#sessions',
            'moreCount' => $total > count($page['sessions']) ? min(self::MORE, $total - count($page['sessions'])) : self::MORE,
            'total' => $total > 0 ? Format::number($total).' sessions recorded' : null,
            'days' => self::days($days),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private static function row(array $row, string $base): array
    {
        [$from, $to] = $row['level'];

        return [
            'href' => $base.'/sessions/'.$row['start'],
            'when' => Dates::moment($row['start']),
            'title' => $row['title']['text'],
            'played' => Format::duration($row['played']),
            'kills' => $row['kills'] === null ? '' : Format::number($row['kills']).' kills',
            'level' => $from === $to ? (string) $from : $from.' → '.$to,
        ];
    }

    /**
     * @param  array<string, int>  $days
     * @return array{cells: list<array{level: int, tip: string}|null>, played: int, longest: int}
     */
    private static function days(array $days): array
    {
        $today = Carbon::now('UTC')->startOfDay();
        $start = $today->copy()->startOfWeek()->subWeeks(self::WEEKS - 1);
        $cells = [];
        $played = $run = $longest = 0;
        for ($day = $start->copy(); $day <= $today->copy()->endOfWeek(); $day->addDay()) {
            if ($day > $today) {
                $cells[] = null;

                continue;
            }
            $hours = ($days[$day->toDateString()] ?? 0) / 3600;
            $level = 0;
            foreach (self::STEPS as $i => $from) {
                $level = $hours > $from ? $i + 1 : $level;
            }
            $played += $hours > 0 ? 1 : 0;
            $run = $hours > 0 ? $run + 1 : 0;
            $longest = max($longest, $run);
            $cells[] = ['level' => $level, 'tip' => Dates::text($day->toDateString(), DateForm::Weekday).'|'.($hours > 0 ? Format::hours($hours * 3600).' played' : 'Not played')];
        }

        return ['cells' => $cells, 'played' => $played, 'longest' => $longest];
    }
}
