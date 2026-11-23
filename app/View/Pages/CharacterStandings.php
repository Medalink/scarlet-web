<?php

namespace App\View\Pages;

use App\Support\Board;
use App\View\Format;

/** The Standings section of the character page, from the `standings` of App\Actions\Public\CharacterOverview. */
final class CharacterStandings
{
    /** The places that carry a medal: gold, silver and copper. */
    private const int MEDALS = 3;

    /**
     * @param  list<array{board: string, title: string, place: int, total: int, value: int|float}>  $standings
     * @return list<array{place: int, total: int, medal: string|null, title: string, href: string, value: string, of: string}>
     */
    public static function rows(array $standings): array
    {
        return array_map(function (array $row): array {
            $board = Board::from($row['board']);

            return [
                'place' => $row['place'],
                'total' => $row['total'],
                'medal' => $row['place'] <= self::MEDALS ? 'p'.$row['place'] : null,
                'title' => $row['title'],
                'href' => LeaderboardPage::link($board),
                'value' => self::value($board, $row['value']),
                'of' => Format::count($row['total'], 'character'),
            ];
        }, $standings);
    }

    private static function value(Board $board, int|float $value): string
    {
        if ($board->level() !== null) {
            return $board->format($value).' played';
        }
        if ($board === Board::HighestWithoutDying) {
            return 'level '.$board->format($value);
        }
        if ($board === Board::MostTimeByTheFire) {
            return $board->format($value).' by the fire';
        }
        if ($board === Board::MostDistance) {
            $shown = $board->format($value);

            return $shown.' '.($shown === '1.0' ? 'world width' : 'world widths');
        }
        if ($board === Board::MostFishCaught) {
            return $board->format($value).' fish';
        }
        if ($board === Board::FeatScore) {
            return Format::count($value, 'point');
        }
        if ($board === Board::MostQuestsCompleted) {
            return Format::count($value, 'quest').' completed';
        }

        $noun = match ($board) {
            Board::MostQuests => 'quest',
            Board::MostKills => 'kill',
            Board::MostJumps => 'jump',
            Board::MostDungeonRuns => 'run',
            Board::MostHerbsGathered => 'herb',
            Board::MostOreMined => 'strike',
            Board::MostCreaturesSkinned => 'creature',
            default => 'craft',
        };

        return Format::count($value, $noun);
    }
}
