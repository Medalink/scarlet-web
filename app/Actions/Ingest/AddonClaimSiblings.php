<?php

namespace App\Actions\Ingest;

use Illuminate\Support\Facades\DB;

class AddonClaimSiblings
{
    /**
     * The characters whose claims one reconcile reads together (docs/DESIGN.md, Reconcile, rule 4): the character and
     * every character linked to it, directly or through another, that holds claims. Two characters are linked when
     * they belong to one account or one install uploaded them. The ids come back lowest first, which is the order
     * their locks are taken in. Empty when none of them holds a claim.
     *
     * @return list<int>
     */
    public function handle(int $characterId): array
    {
        $reached = [$characterId];
        $frontier = [$characterId];
        while ($frontier !== []) {
            $found = array_values(array_diff($this->linkedWithClaims($frontier), $reached));
            $reached = [...$reached, ...$found];
            $frontier = $found;
        }

        $holders = DB::table('addon_claims')->whereIn('character_id', $reached)->distinct()->orderBy('character_id')->pluck('character_id');

        return array_values($holders->map(fn (mixed $id): int => (int) $id)->all());
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function linkedWithClaims(array $ids): array
    {
        $byAccount = DB::table('characters as linked')
            ->join('characters as known', 'known.account_id', '=', 'linked.account_id')
            ->whereIn('known.id', $ids)
            ->whereNotNull('known.account_id')
            ->select('linked.id');
        $byInstall = DB::table('install_character as linked')
            ->join('install_character as known', 'known.install_id', '=', 'linked.install_id')
            ->whereIn('known.character_id', $ids)
            ->select('linked.character_id as id');

        $linked = DB::query()->fromSub($byAccount->union($byInstall), 'links')
            ->whereExists(fn ($claims) => $claims->selectRaw('1')->from('addon_claims')->whereColumn('addon_claims.character_id', 'links.id'))
            ->distinct()
            ->pluck('id');

        return array_values($linked->map(fn (mixed $id): int => (int) $id)->all());
    }
}
