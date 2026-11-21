<?php

namespace App\Actions\Ingest;

use App\Actions\Rulesets\ResolveCharacterKind;
use App\Models\Ruleset;

class ResolveRuleset
{
    public function __construct(private readonly ResolveCharacterKind $kinds) {}

    /**
     * The ruleset row for the signals of a session and the kind of its character (docs/DESIGN.md, Rulesets), made
     * when it is new.
     *
     * @param  array<string, mixed>  $summary  the decoder's `sessions` row
     */
    public function handle(int $characterId, array $summary): Ruleset
    {
        $signals = [
            'preset' => isset($summary['gr_preset']) ? (int) $summary['gr_preset'] : null,
            'hardcore' => isset($summary['gr_hardcore']) ? (bool) $summary['gr_hardcore'] : null,
            'war_mode' => (string) ($summary['war_mode'] ?? Ruleset::WAR_MODE_UNKNOWN),
            'xp_off' => isset($summary['xp_off']) ? (bool) $summary['xp_off'] : null,
            'game_mode' => isset($summary['game_mode']) ? (int) $summary['game_mode'] : null,
            'mode_record' => isset($summary['mode_record']) ? (int) $summary['mode_record'] : null,
            'kind' => $this->kinds->handle($characterId, isset($summary['server_id']) ? (int) $summary['server_id'] : null)?->value,
        ];
        $key = Ruleset::keyFor($signals);

        Ruleset::query()->insertOrIgnore(['key' => $key] + $signals);

        return Ruleset::query()->where('key', $key)->firstOrFail();
    }
}
