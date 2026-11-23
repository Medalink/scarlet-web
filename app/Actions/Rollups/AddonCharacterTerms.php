<?php

namespace App\Actions\Rollups;

use App\Support\AddonKind;
use App\Support\AddonOrigin;
use App\Support\CharacterScope;
use App\Support\ClaimStatus;
use App\Support\RollupRead;
use Illuminate\Database\Query\Builder;

class AddonCharacterTerms
{
    private const string SCOPE_CHARACTER = 'c';

    public function __construct(private readonly RollupRead $read) {}

    /**
     * What other addons counted of one character's kills, quests and dungeon runs (docs/DESIGN.md, Addon counts,
     * Reading), one query for each: the counted `kills` of the `rxp` kill claims that count or count in part, one
     * for each `rxp` quest claim that counts, and the counted `runs` of the `fdj` run claims that count or count in
     * part. A claim is read only while no addon day of the character lacks a reconcile version. A quest claim adds a
     * turn-in, never its XP: the page shows no quest XP total.
     *
     * @return array{kills: int, quests: int, runs: int}
     */
    public function handle(int $characterId): array
    {
        $scope = new CharacterScope([$characterId], null);

        return [
            'kills' => $this->total($this->claims($scope, AddonKind::Kill, AddonOrigin::RxpGuides, ClaimStatus::adding())
                ->selectRaw('coalesce(sum((counted ->> ?)::bigint), 0) as total', ['kills'])),
            'quests' => $this->total($this->claims($scope, AddonKind::Xp, AddonOrigin::RxpGuides, [ClaimStatus::Counted->value])
                ->selectRaw('count(*) as total')),
            'runs' => $this->total($this->claims($scope, AddonKind::Run, AddonOrigin::ForeverDungeonJournal, ClaimStatus::adding())
                ->selectRaw('coalesce(sum((counted ->> ?)::bigint), 0) as total', ['runs'])),
        ];
    }

    private function total(Builder $query): int
    {
        return (int) ($this->read->rows($query)[0]['total'] ?? 0);
    }

    /** @param  list<string>  $statuses */
    private function claims(CharacterScope $scope, AddonKind $kind, AddonOrigin $origin, array $statuses): Builder
    {
        return $this->read->table('addon_claims', $scope)
            ->where('kind', $kind->value)
            ->where('origin', $origin->value)
            ->where('scope', self::SCOPE_CHARACTER)
            ->whereIn('status', $statuses)
            ->whereNotExists(fn (Builder $days): Builder => $days->selectRaw('1')->from('addon_days')
                ->whereColumn('addon_days.character_id', 'addon_claims.character_id')->whereNull('addon_days.reconcile_version'));
    }
}
