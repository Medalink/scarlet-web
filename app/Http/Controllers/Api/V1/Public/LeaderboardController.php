<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Public\Leaderboard;
use App\Http\Requests\Api\PublicRequest;
use App\Support\Board;
use App\Support\Cursor;
use Illuminate\Http\JsonResponse;

/** The leaderboards of public characters. */
class LeaderboardController extends PublicController
{
    public function index(PublicRequest $request): JsonResponse
    {
        return $this->respond(array_map(fn (Board $board): array => [
            'board' => $board->value, 'title' => $board->title(), 'description' => $board->description(), 'value' => $board->column(),
        ], Board::cases()));
    }

    public function show(PublicRequest $request, Leaderboard $leaderboard, string $board): JsonResponse
    {
        $kind = Board::tryFrom($board);
        if ($kind === null) {
            $this->missing('The board');
        }
        $offset = $request->offset();
        $limit = $request->limit();
        $answer = $leaderboard->handle($kind, $request->rulesetSlug(), $request->serverFilter(), $request->characterClass(), $offset, $limit);
        if ($answer === null) {
            $this->missing('The ruleset');
        }

        [$rows, $next] = Cursor::page($answer['rows'], $offset, $limit);

        return $this->respondPage(
            array_map(fn (mixed $row): array => self::row($row), $rows),
            $next,
            ['board' => $kind->value, 'title' => $kind->title(), 'value' => $kind->column(), 'total' => $answer['total']],
        );
    }

    /**
     * A board row without the viewer flag: the API answers the same to everyone.
     *
     * @return array<string, mixed>
     */
    private static function row(mixed $row): array
    {
        $row = is_array($row) ? $row : [];
        unset($row['mine'], $row['ruleset_label']);

        return $row;
    }
}
