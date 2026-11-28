<?php

namespace App\Actions\Account;

use App\Models\Account;
use App\Models\Ruleset;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ShowCharacters
{
    public function __construct(private readonly ShowStorage $storage) {}

    /**
     * The account's characters by name, with what the account page lists and the privacy modal switches: the page's
     * three switches, the ruleset of the latest session, played time, and the parts and size stored.
     *
     * @return list<array{id: int, name: string, class: string|null, race: string|null, level: int|null, ruleset: string|null, played_seconds: int, sessions: int, is_public: bool, show_economy: bool, show_sessions: bool, parts: int, bytes: int, last_upload: Carbon|null}>
     */
    public function handle(Account $account): array
    {
        $characters = $account->characters()
            ->select(['id', 'name', 'class', 'race', 'level', 'played_seconds', 'played_known', 'recorded_seconds', 'sessions_decoded', 'is_public', 'show_economy', 'show_sessions'])
            ->orderBy('name')
            ->get();
        $latest = DB::table('sessions')->whereIn('character_id', $characters->pluck('id')->all())->orderBy('character_id')->orderByDesc('end_at')
            ->selectRaw('distinct on (character_id) character_id, ruleset_id')->get()->keyBy('character_id');
        $rulesets = Ruleset::query()->whereIn('id', $latest->pluck('ruleset_id')->unique()->all())->get()->keyBy('id');
        $stored = array_column($this->storage->handle($account)['characters'], null, 'id');

        $rows = [];
        foreach ($characters as $character) {
            $ruleset = isset($latest[$character->id]) ? $rulesets->get((int) $latest[$character->id]->ruleset_id) : null;
            $rows[] = [
                'id' => $character->id,
                'name' => $character->name,
                'class' => $character->class,
                'race' => $character->race,
                'level' => $character->level ?: null,
                'ruleset' => $ruleset?->label(),
                'played_seconds' => (int) ($character->played_known ? $character->played_seconds : $character->recorded_seconds),
                'sessions' => (int) $character->sessions_decoded,
                'is_public' => (bool) $character->is_public,
                'show_economy' => (bool) $character->show_economy,
                'show_sessions' => (bool) $character->show_sessions,
                'parts' => $stored[$character->id]['parts'],
                'bytes' => $stored[$character->id]['bytes'],
                'last_upload' => $stored[$character->id]['last'],
            ];
        }

        return $rows;
    }
}
