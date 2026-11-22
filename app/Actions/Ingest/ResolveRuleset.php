<?php

namespace App\Actions\Ingest;

use App\Actions\Rulesets\ResolveCharacterKind;
use App\Models\Ruleset;
use App\Models\RulesetKind;

class ResolveRuleset
{
    public function __construct(private readonly ResolveCharacterKind $kinds) {}

    /**
     * The ruleset row of a session (docs/DESIGN.md, Rulesets): the one for the kind of its character, made when it is
     * new, or the one for characters whose kind is not known yet. The session keeps its other signals (war mode,
     * experience off, game mode, record) as its own data; none of them is part of the ruleset.
     *
     * @param  array<string, mixed>  $summary  the decoder's `sessions` row
     */
    public function handle(int $characterId, array $summary): Ruleset
    {
        $kind = RulesetKind::of(
            isset($summary['gr_hardcore']) ? (bool) $summary['gr_hardcore'] : null,
            isset($summary['mode_record']) ? (int) $summary['mode_record'] : null,
        ) ?? $this->kinds->handle($characterId, isset($summary['server_id']) ? (int) $summary['server_id'] : null);
        $key = Ruleset::keyFor($kind);

        Ruleset::query()->insertOrIgnore([
            'key' => $key,
            'war_mode' => $kind === null ? Ruleset::WAR_MODE_UNKNOWN : $kind->warMode(),
            'kind' => $kind?->value,
        ]);

        return Ruleset::query()->where('key', $key)->firstOrFail();
    }
}
