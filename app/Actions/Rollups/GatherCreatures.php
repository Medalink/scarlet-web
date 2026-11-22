<?php

namespace App\Actions\Rollups;

use App\Support\GatherKind;
use App\Support\NameKind;
use App\Support\Rollup;
use App\Support\RollupRead;

class GatherCreatures
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * The creatures a gathering took from, by the id of the loot window's source, most gathers first. A creature fewer
     * than `config('scarlet.min_characters')` characters took from is left out.
     *
     * @param  int|list<int>|null  $rulesetId
     * @return list<array{npc_id: int, name: string|null, gathers: int, characters: int|null}>
     */
    public function handle(GatherKind $kind, int|array|null $rulesetId = null, int $limit = 10): array
    {
        $query = $this->read->from(Rollup::GathersDailySourceRuleset, $rulesetId)
            ->where('kind', $kind->value)
            ->selectRaw('source_id as npc_id, (select n.name from names n where n.kind = ? and n.id = source_id order by n.votes desc, n.name limit 1) as name, sum(attempts) as gathers, distinct_count(rollup(characters)) as characters', [NameKind::Npc->value])
            ->groupBy('source_id')
            ->orderByRaw('sum(attempts) desc')
            ->orderBy('source_id')
            ->limit($limit);

        return array_map(fn (array $row): array => [
            'npc_id' => (int) $row['npc_id'],
            'name' => $row['name'] === null ? null : (string) $row['name'],
            'gathers' => (int) $row['gathers'],
            'characters' => (int) $row['characters'],
        ], $this->read->rows($this->read->shown($query)));
    }
}
