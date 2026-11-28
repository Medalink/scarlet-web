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
     * `xp` is the experience bar of the latest session that read one, null when none did. Money is in the records only
     * with `$economy`.
     *
     * @return array{xp: array{xp: int, max: int}|null, skills: list<array{name: string, rank: int, max: int, kind: string}>, reputation: list<array{id: int, name: string, tier: int, standing: int, from: int, to: int}>, records: list<array{key: string, value: int|float, at: string, from: int|null, to: int|null, item: string|null}>}
     */
    public function handle(Character $character, bool $economy): array
    {
        return [
            'xp' => $this->xp($character->id),
            'skills' => $this->skills->handle($character->id),
            'reputation' => $this->reputation->handle($character->id),
            'records' => $this->records->handle($character->id, $economy),
        ];
    }

    /** @return array{xp: int, max: int}|null */
    private function xp(int $characterId): ?array
    {
        $bar = DB::table('sessions')->where('character_id', $characterId)->whereRaw("(extra->>'xp_max') is not null")->orderByDesc('end_at')
            ->selectRaw("(extra->>'xp')::bigint as xp, (extra->>'xp_max')::bigint as max")->first();

        return $bar === null || (int) $bar->max <= 0 ? null : ['xp' => (int) $bar->xp, 'max' => (int) $bar->max];
    }
}
