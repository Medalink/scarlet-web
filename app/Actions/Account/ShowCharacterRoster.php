<?php

namespace App\Actions\Account;

use App\Models\Account;
use Illuminate\Support\Facades\DB;

class ShowCharacterRoster
{
    /**
     * Every character of the account, most played first, with its latest ruleset and balance and the kills and deaths
     * its counted sessions recorded. `played_seconds` is the client's total, null when no session read it; `recorded_seconds`
     * is what the sessions add up to.
     *
     * @return list<array{id: int, name: string, class: string|null, race: string|null, level: int|null, played_seconds: int|null, recorded_seconds: int, sessions: int, last_seen_at: string|null, is_public: bool, ruleset_id: int|null, balance: int|null, kills: int, deaths: int}>
     */
    public function handle(Account $account): array
    {
        $characters = $account->characters()
            ->select(['id', 'name', 'class', 'race', 'level', 'played_seconds', 'played_known', 'recorded_seconds', 'sessions_decoded', 'last_seen_at', 'is_public'])
            ->orderByDesc('played_seconds')->orderBy('name')->get();
        $ids = $characters->pluck('id')->all();
        $rulesets = DB::table('sessions')->whereIn('character_id', $ids)->orderBy('character_id')->orderByDesc('end_at')
            ->selectRaw('distinct on (character_id) character_id, ruleset_id')->get()->keyBy('character_id');
        $balances = DB::table('sessions')->whereIn('character_id', $ids)->whereNotNull('money_end')->orderBy('character_id')->orderByDesc('end_at')
            ->selectRaw('distinct on (character_id) character_id, money_end')->get()->keyBy('character_id');
        $sums = DB::table('sessions')->whereIn('character_id', $ids)->where('counted', true)->groupBy('character_id')
            ->selectRaw('character_id, coalesce(sum(kills), 0) as kills, coalesce(sum(deaths), 0) as deaths')->get()->keyBy('character_id');

        return array_values($characters->map(fn ($character): array => [
            'id' => $character->id,
            'name' => $character->name,
            'class' => $character->class,
            'race' => $character->race,
            'level' => $character->level,
            'played_seconds' => $character->played_known ? (int) $character->played_seconds : null,
            'recorded_seconds' => (int) $character->recorded_seconds,
            'sessions' => (int) $character->sessions_decoded,
            'last_seen_at' => $character->last_seen_at?->toIso8601ZuluString(),
            'is_public' => (bool) $character->is_public,
            'ruleset_id' => isset($rulesets[$character->id]) ? (int) $rulesets[$character->id]->ruleset_id : null,
            'balance' => isset($balances[$character->id]) ? (int) $balances[$character->id]->money_end : null,
            'kills' => (int) ($sums[$character->id]->kills ?? 0),
            'deaths' => (int) ($sums[$character->id]->deaths ?? 0),
        ])->all());
    }
}
