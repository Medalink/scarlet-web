<?php

namespace App\Actions\Public\Character;

use App\Models\Character;
use App\Support\Board;
use App\Support\BoardQuery;
use App\Support\PageScope;
use App\Support\Server;
use Illuminate\Support\Facades\DB;

class Standings
{
    public function __construct(private readonly BoardQuery $boards) {}

    /**
     * Where the character stands on each board it is on, among public characters of every ruleset and server, best place
     * first and in the order of the boards for equal places. A character that is not public is on no board.
     *
     * @return list<array{board: string, title: string, place: int, total: int, value: int|float}>
     */
    public function handle(Character $character): array
    {
        if (! $character->is_public) {
            return [];
        }
        $scope = new PageScope(null, Server::All, null);
        $rows = [];
        foreach (Board::cases() as $board) {
            $row = DB::query()->fromSub($this->boards->placed($board, $scope), 'b')->where('b.character_id', $character->id)->first(['b.place', 'b.total', 'b.value']);
            if ($row === null) {
                continue;
            }
            $rows[] = ['board' => $board->value, 'title' => $board->title(), 'place' => (int) $row->place, 'total' => (int) $row->total, 'value' => $this->boards->value($row->value)];
        }
        usort($rows, fn (array $a, array $b): int => $a['place'] <=> $b['place']);

        return $rows;
    }
}
