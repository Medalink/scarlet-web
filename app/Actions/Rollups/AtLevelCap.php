<?php

namespace App\Actions\Rollups;

use App\Support\Rollup;
use App\Support\RollupRead;

class AtLevelCap
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * What Scarlet recorded at one level, the cap, since it first saw each character: play time, deaths, dungeon
     * runs, and copper earned and spent from the character's own purse. A character first seen at the cap counts
     * only from then. Each figure carries `characters`, how many characters it rests on: the ones with recorded play at
     * the cap, and for money the ones with a recorded purse there. A figure that fewer than
     * `config('scarlet.min_characters')` characters rest on is null.
     *
     * @param  int|list<int>|null  $rulesetId
     * @return array{seconds: array<string, int|float>|null, deaths: array<string, int|float>|null, runs: array<string, int|float>|null, money: array<string, int|float>|null}
     */
    public function handle(int $level, int|array|null $rulesetId = null): array
    {
        $played = $this->read->rows($this->read->from(Rollup::PlayHourlyRuleset, $rulesetId)
            ->selectRaw('(sum(ms) / 1000.0)::float as seconds, distinct_count(rollup(characters)) as characters')
            ->where('level', $level))[0] ?? null;
        $deaths = $this->read->rows($this->read->from(Rollup::DeathsDailyRuleset, $rulesetId)
            ->selectRaw('coalesce(sum(deaths), 0)::bigint as deaths')
            ->where('level', $level))[0] ?? null;
        $runs = $this->read->rows($this->read->table('dungeon_runs', $rulesetId)
            ->where('counted', true)->where('continues_previous', false)->where('level', $level)
            ->selectRaw('count(*) as runs'))[0] ?? null;
        $money = $this->read->rows($this->read->table('money_changes', $rulesetId)
            ->where('counted', true)->where('src', 'M')->where('level', $level)
            ->selectRaw('coalesce(sum(earned), 0)::bigint as earned, coalesce(sum(spent), 0)::bigint as spent, count(distinct character_id) as characters'))[0] ?? null;

        $min = (int) config('scarlet.min_characters');
        $playedBy = (int) ($played['characters'] ?? 0);
        $enough = fn (?array $row, int $characters): ?array => $row !== null && $characters >= $min ? [...$row, 'characters' => $characters] : null;

        return [
            'seconds' => $enough($played, $playedBy),
            'deaths' => $enough($deaths, $playedBy),
            'runs' => $enough($runs, $playedBy),
            'money' => $enough($money, (int) ($money['characters'] ?? 0)),
        ];
    }
}
