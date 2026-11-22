<?php

namespace App\Actions\Rollups;

use App\Support\AddonKind;
use App\Support\CharacterScope;
use App\Support\ClaimStatus;
use App\Support\RollupRead;
use Illuminate\Database\Query\Builder;

class AddonFishingTerms
{
    private const string SCOPE_CHARACTER = 'c';

    private const string LIFETIME = 'life';

    private const array METRICS = ['casts', 'landed', 'items'];

    public function __construct(private readonly RollupRead $read) {}

    /**
     * What other addons counted of fishing in the scope (docs/DESIGN.md, Addon counts, Reading): the counted amounts of
     * the character-scope claims that add, the dated ones and the undated (lifetime) ones as one query each. A claim is
     * read only while no addon day of its character lacks a reconcile version. A claim with no ruleset is not in a scope
     * narrowed to rulesets. Null when no character has a counted claim, or fewer than the scope's threshold of
     * characters do: a total that included them would show what those few characters counted. `characters` is the
     * larger of the two terms' counts, never their sum, so it never overstates the characters behind the amounts.
     *
     * @param  int|list<int>|CharacterScope|null  $scope
     * @return array{dated: array{casts: int, landed: int, items: int}, undated: array{casts: int, landed: int, items: int}, characters: int}|null
     */
    public function handle(int|array|CharacterScope|null $scope = null): ?array
    {
        $dated = $this->term($scope, true);
        $undated = $this->term($scope, false);
        $characters = max($dated['characters'], $undated['characters']);
        if ($characters === 0 || $characters < $this->read->threshold($scope)) {
            return null;
        }

        return [
            'dated' => ['casts' => $dated['casts'], 'landed' => $dated['landed'], 'items' => $dated['items']],
            'undated' => ['casts' => $undated['casts'], 'landed' => $undated['landed'], 'items' => $undated['items']],
            'characters' => $characters,
        ];
    }

    /**
     * @param  int|list<int>|CharacterScope|null  $scope
     * @return array{casts: int, landed: int, items: int, characters: int}
     */
    private function term(int|array|CharacterScope|null $scope, bool $dated): array
    {
        $query = $this->claims($scope)->where('window', $dated ? '<>' : '=', self::LIFETIME)->selectRaw(
            'count(distinct character_id) as characters, coalesce(sum((counted ->> ?)::bigint), 0) as casts,'
            .' coalesce(sum((counted ->> ?)::bigint), 0) as landed, coalesce(sum((counted ->> ?)::bigint), 0) as items',
            self::METRICS,
        );
        $row = $this->read->rows($query)[0] ?? [];

        return [
            'casts' => (int) ($row['casts'] ?? 0),
            'landed' => (int) ($row['landed'] ?? 0),
            'items' => (int) ($row['items'] ?? 0),
            'characters' => (int) ($row['characters'] ?? 0),
        ];
    }

    /** @param  int|list<int>|CharacterScope|null  $scope */
    private function claims(int|array|CharacterScope|null $scope): Builder
    {
        return $this->read->table('addon_claims', $scope)
            ->where('kind', AddonKind::Fishing->value)
            ->where('scope', self::SCOPE_CHARACTER)
            ->whereIn('status', ClaimStatus::adding())
            ->whereNotExists(fn (Builder $days): Builder => $days->selectRaw('1')->from('addon_days')
                ->whereColumn('addon_days.character_id', 'addon_claims.character_id')->whereNull('addon_days.reconcile_version'));
    }
}
