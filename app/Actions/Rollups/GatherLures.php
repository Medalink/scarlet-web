<?php

namespace App\Actions\Rollups;

use App\Support\CharacterScope;
use App\Support\GatherKind;
use App\Support\NameKind;
use App\Support\Rollup;
use App\Support\RollupRead;

class GatherLures
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * The casts made within `GatherKind::LURE_SECONDS` of applying a lure, by lure, most first: every lure with enough
     * characters is counted in `casts`, and `lures` lists the first `$limit`. A lure fewer than
     * the scope's threshold of characters used is left out.
     *
     * @param  int|list<int>|CharacterScope|null  $scope
     * @return array{casts: int, lures: list<array{spell_id: int, name: string|null, casts: int}>}
     */
    public function handle(GatherKind $kind, int|array|CharacterScope|null $scope = null, int $limit = 6): array
    {
        $query = $this->read->from(Rollup::GathersDailyGearRuleset, $scope)
            ->where('kind', $kind->value)
            ->whereNotNull('lure_spell')
            ->where('lure_minutes', '<=', intdiv(GatherKind::LURE_SECONDS, 60))
            ->selectRaw('lure_spell as spell_id, (select n.name from names n where n.kind = ? and n.id = lure_spell order by n.votes desc, n.name limit 1) as name, sum(casts) as casts', [NameKind::Spell->value])
            ->groupBy('lure_spell')
            ->orderByRaw('sum(casts) desc')
            ->orderBy('lure_spell');

        $lures = array_map(fn (array $row): array => [
            'spell_id' => (int) $row['spell_id'],
            'name' => $row['name'] === null ? null : (string) $row['name'],
            'casts' => (int) $row['casts'],
        ], $this->read->rows($this->read->shown($query, $scope)));

        return ['casts' => array_sum(array_column($lures, 'casts')), 'lures' => array_slice($lures, 0, $limit)];
    }
}
