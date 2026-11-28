<?php

namespace App\Actions\Public;

use App\Actions\Public\Character\ReputationGoals;
use App\Actions\Public\Character\SessionRecords;
use App\Actions\Public\Character\SkillGoals;
use App\Models\Character;
use Illuminate\Support\Facades\DB;

/** What the Level, Skills, Reputation and Records of the character page draw. The public API does not carry it. */
class CharacterGoals
{
    public function __construct(
        private readonly SkillGoals $skills,
        private readonly ReputationGoals $reputation,
        private readonly SessionRecords $records,
    ) {}

    /**
     * `xp` is the experience bar of the latest counted session that ended at the character's current level, null when that session read none. Money is in the records only
     * with `$economy`.
     *
     * @return array{xp: array{xp: int, max: int}|null, skills: list<array{name: string, rank: int, max: int, kind: string}>, reputation: list<array{id: int, name: string, tier: int, standing: int, from: int, to: int}>, records: list<array{key: string, value: int|float, at: string, from: int|null, to: int|null, item: string|null}>}
     */
    public function handle(Character $character, bool $economy): array
    {
        return [
            'xp' => $this->xp($character),
            'skills' => $this->skills->handle($character->id),
            'reputation' => $this->reputation->handle($character->id),
            'records' => $this->records->handle($character->id, $economy),
        ];
    }

    /** @return array{xp: int, max: int}|null the bar of the latest counted session that ended at the character's level, null when that session has none */
    private function xp(Character $character): ?array
    {
        if ($character->level === null) {
            return null;
        }
        $bar = DB::table('sessions')->where('character_id', $character->id)->where('counted', true)->where('level_end', $character->level)->orderByDesc('end_at')
            ->selectRaw("(extra->>'xp')::bigint as xp, (extra->>'xp_max')::bigint as max")->first();

        return $bar === null || $bar->max === null || (int) $bar->max <= 0 ? null : ['xp' => (int) $bar->xp, 'max' => (int) $bar->max];
    }
}
