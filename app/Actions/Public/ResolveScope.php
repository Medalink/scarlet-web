<?php

namespace App\Actions\Public;

use App\Models\Ruleset;
use App\Support\PageScope;
use App\Support\Server;
use Illuminate\Support\Facades\DB;

class ResolveScope
{
    /**
     * The rulesets a page reads: the one whose slug is given, else the ones of the chosen server, else all.
     * A slug from before the kind was part of a ruleset's identity (no `-kn` at its end) names the oldest row that has
     * it, which is the row those addresses always showed. Null when the slug names no ruleset.
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

        $ruleset = ctype_digit($rulesetSlug) ? Ruleset::query()->find((int) $rulesetSlug) : $this->bySlug($rulesetSlug);

        return $ruleset === null ? null : new PageScope($ruleset, Server::All, $ruleset->id);
    }

    /** The row whose slug is this one, else the row an address without a kind meant: one that has sessions, then the oldest. */
    private function bySlug(string $slug): ?Ruleset
    {
        $matches = Ruleset::query()->orderBy('id')->get()->filter(fn (Ruleset $candidate): bool => $candidate->slug() === $slug || $candidate->slugWithoutKind() === $slug);
        $filed = DB::table('sessions')->whereIn('ruleset_id', $matches->modelKeys())->distinct()->pluck('ruleset_id')->map(fn (mixed $id): int => (int) $id)->all();

        return $matches->sortBy(fn (Ruleset $candidate): array => [in_array($candidate->id, $filed, true) ? 0 : 1, $candidate->slug() === $slug ? 0 : 1, $candidate->id])->first();
    }
}
