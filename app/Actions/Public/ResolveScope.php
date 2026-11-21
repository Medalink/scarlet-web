<?php

namespace App\Actions\Public;

use App\Models\Ruleset;
use App\Support\PageScope;
use App\Support\Server;

class ResolveScope
{
    /**
     * The rulesets a page reads: the one whose slug is given, else the ones of the chosen server, else all.
     * Null when the slug names no ruleset.
     */
    public function handle(?string $rulesetSlug, Server $server = Server::All): ?PageScope
    {
        if ($rulesetSlug === null || $rulesetSlug === '') {
            $mode = $server->warMode();
            if ($mode === null) {
                return new PageScope(null, $server, null);
            }

            return new PageScope(null, $server, array_values(Ruleset::query()->where('war_mode', $mode)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all()));
        }

        $ruleset = ctype_digit($rulesetSlug)
            ? Ruleset::query()->find((int) $rulesetSlug)
            : Ruleset::query()->get()->first(fn (Ruleset $candidate): bool => $candidate->slug() === $rulesetSlug);

        return $ruleset === null ? null : new PageScope($ruleset, Server::All, $ruleset->id);
    }
}
