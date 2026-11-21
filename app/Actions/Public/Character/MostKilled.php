<?php

namespace App\Actions\Public\Character;

use App\Support\NameKind;
use App\Support\Rollup;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class MostKilled
{
    /** Creatures the page lists. */
    public const int SHOWN = 5;

    /**
     * The creatures a character killed most, from the daily kill aggregate. A creature nobody has named is left out.
     *
     * @return list<array{id: int, name: string, kills: int}>
     */
    public function handle(int $characterId): array
    {
        $table = Rollup::KillsDailyCharacter->value;
        $rows = DB::table($table)->where('character_id', $characterId)
            ->whereExists(fn (Builder $name): Builder => $name->selectRaw('1')->from('names as n')->where('n.kind', NameKind::Npc->value)->whereColumn('n.id', $table.'.npc_id'))
            ->groupBy('npc_id')
            ->selectRaw('npc_id, sum(kills)::bigint as kills, (select n.name from names n where n.kind = ? and n.id = npc_id order by n.votes desc, n.name limit 1) as name', [NameKind::Npc->value])
            ->orderByRaw('sum(kills) desc')->orderBy('npc_id')->limit(self::SHOWN)->get();

        return array_values($rows->map(fn (object $row): array => ['id' => (int) $row->npc_id, 'name' => (string) $row->name, 'kills' => (int) $row->kills])->all());
    }
}
