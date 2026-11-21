<?php

namespace App\Actions\Public\Character;

use App\Support\CreatureClass;
use App\Support\NameKind;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class CreaturesMet
{
    public const int SHOWN = 5;

    /**
     * @return array{counts: array<string, int>, rares: list<array{id: int, name: string, level: int, class: string}>}
     */
    public function handle(int $characterId): array
    {
        $ranks = [CreatureClass::Elite, CreatureClass::Rare, CreatureClass::RareElite, CreatureClass::WorldBoss];
        $values = array_map(fn (CreatureClass $rank): string => $rank->value, $ranks);
        $sighted = fn (): Builder => DB::table('creature_sightings')->where('character_id', $characterId)->where('counted', true)->whereIn('classification', $values);
        $counts = $sighted()->groupBy('classification')->selectRaw('classification, count(distinct npc_id) as creatures')->pluck('creatures', 'classification');
        $rares = $sighted()->whereIn('classification', [CreatureClass::Rare->value, CreatureClass::RareElite->value])
            ->whereExists(fn (Builder $name): Builder => $name->selectRaw('1')->from('names as n')->where('n.kind', NameKind::Npc->value)->whereColumn('n.id', 'creature_sightings.npc_id'))
            ->groupBy('npc_id')
            ->selectRaw('npc_id, max(npc_level) as level, max(classification) as class, (select n.name from names n where n.kind = ? and n.id = npc_id order by n.votes desc, n.name limit 1) as name', [NameKind::Npc->value])
            ->orderByRaw('max(npc_level) desc nulls last')->orderBy('npc_id')->limit(self::SHOWN)->get();

        return [
            'counts' => array_map(fn (mixed $count): int => (int) $count, $counts->all()),
            'rares' => array_values($rares->map(fn (object $row): array => ['id' => (int) $row->npc_id, 'name' => (string) $row->name, 'level' => (int) $row->level, 'class' => (string) $row->class])->all()),
        ];
    }
}
