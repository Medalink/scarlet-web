<?php

namespace App\Actions\Public;

use App\Models\Ruleset;
use App\Support\PageScope;
use App\Support\Server;

class ResolveScope
{
    /**
     * The rulesets a page reads: the one whose slug is given, else the ones of the chosen server, else all. An address
     * from before a ruleset was its kind (a slug of signals, with or without the kind token, or a row id) names the row
     * that took over that ruleset's sessions. Null when the slug names no ruleset. `$characters` are the characters of one
     * account for a player's own page; null is everyone's.
     *
     * @param  list<int>|null  $characters
     */
    public function handle(?string $rulesetSlug, Server $server = Server::All, ?array $characters = null): ?PageScope
    {
        if ($rulesetSlug === null || $rulesetSlug === '') {
            $mode = $server->warMode();
            if ($mode === null) {
                return new PageScope(null, $server, null, $characters);
            }

            return new PageScope(null, $server, array_values(Ruleset::query()->whereNull('merged_into')->where('war_mode', $mode)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all()), $characters);
        }

        $ruleset = $this->taken(ctype_digit($rulesetSlug) ? Ruleset::query()->find((int) $rulesetSlug) : $this->bySlug($rulesetSlug));

        return $ruleset === null ? null : new PageScope($ruleset, Server::All, $ruleset->id, $characters);
    }

    /** The row that holds the sessions of this one: the row itself, or the row it was merged into. */
    private function taken(?Ruleset $ruleset): ?Ruleset
    {
        return $ruleset?->merged_into === null ? $ruleset : Ruleset::query()->find($ruleset->merged_into);
    }

    /** The row whose slug is this one; else a row from before kinds, the exact slug first, then the oldest. */
    private function bySlug(string $slug): ?Ruleset
    {
        $matches = Ruleset::query()->orderBy('id')->get()->filter(fn (Ruleset $candidate): bool => $candidate->slug() === $slug || (! $candidate->isKindRow() && $candidate->slugWithoutKind() === $slug));

        return $matches->sortBy(fn (Ruleset $candidate): array => [$candidate->isKindRow() ? 0 : 1, $candidate->slug() === $slug ? 0 : 1, $candidate->id])->first();
    }
}
