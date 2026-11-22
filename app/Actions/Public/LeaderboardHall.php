<?php

namespace App\Actions\Public;

use App\Support\Board;
use App\Support\PageScope;
use App\Support\Server;

class LeaderboardHall
{
    public function __construct(private readonly Leaderboard $leaderboard) {}

    /**
     * Every board with the row that leads it and the number of public characters on it, for one ruleset or server.
     * Null when the ruleset is not known.
     *
     * @return array{scope: PageScope, boards: list<array{board: Board, lead: array<string, mixed>|null, total: int}>}|null
     */
    public function handle(?string $ruleset, Server $server): ?array
    {
        $answers = array_map(fn (Board $board): ?array => $this->leaderboard->handle($board, $ruleset, $server, null, 0, 1), Board::cases());
        $first = $answers[0];
        if ($first === null) {
            return null;
        }

        return [
            'scope' => $first['scope'],
            'boards' => array_values(array_filter(array_map(
                fn (?array $answer): ?array => $answer === null ? null : ['board' => $answer['board'], 'lead' => $answer['rows'][0] ?? null, 'total' => $answer['total']],
                $answers,
            ))),
        ];
    }
}
