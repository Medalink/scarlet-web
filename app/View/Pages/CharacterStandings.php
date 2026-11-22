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
     * @return list<array{place: int, medal: string|null, title: string, href: string, value: string, of: string}>
     */
    public static function rows(array $standings): array
    {
        return array_map(function (array $row): array {
            $board = Board::from($row['board']);

            return [
                'place' => $row['place'],
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

        return $board->format($value).' '.strtolower($board->column());
    }
}
