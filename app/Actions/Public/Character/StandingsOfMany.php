<?php

namespace App\Actions\Public\Character;

use App\Models\Character;
use App\Support\Board;
use App\Support\BoardQuery;
use App\Support\PageScope;
use App\Support\Server;
use Illuminate\Support\Facades\DB;

/** `Standings` for several characters with one query for each board, for the pages that list the places of a whole account. */
class StandingsOfMany
{
    public function __construct(private readonly BoardQuery $boards) {}

    /**
     * @param  list<Character>  $characters
     * @return array<int, list<array{board: string, title: string, place: int, total: int, value: int|float}>> by character id, each best place first and in the order of the boards for equal places
     */
    public function handle(array $characters): array
    {
        $found = [];
        $public = [];
        foreach ($characters as $character) {
            $found[$character->id] = [];
            if ($character->is_public) {
                $public[] = $character->id;
            }
        }
        if ($public === []) {
            return $found;
        }
        $scope = new PageScope(null, Server::All, null);
        foreach (Board::cases() as $board) {
            $rows = DB::query()->fromSub($this->boards->placed($board, $scope), 'b')->whereIn('b.character_id', $public)->get(['b.character_id', 'b.place', 'b.total', 'b.value']);
            foreach ($rows as $row) {
                $found[(int) $row->character_id][] = ['board' => $board->value, 'title' => $board->title(), 'place' => (int) $row->place, 'total' => (int) $row->total, 'value' => $this->boards->value($row->value)];
            }
        }
        foreach ($found as $id => $rows) {
            usort($rows, fn (array $a, array $b): int => $a['place'] <=> $b['place']);
            $found[$id] = $rows;
        }

        return $found;
    }
}
