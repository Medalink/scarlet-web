<?php

namespace App\Actions\Account;

use App\Actions\Sessions\BuildRecap;
use App\Models\Account;
use Illuminate\Support\Facades\DB;

/** The newest counted session across the characters of an account, as a recap, for the card on the front page. */
class ShowLatestSession
{
    public function __construct(private readonly BuildRecap $recap) {}

    /** @return array{name: string, class: string, recap: array<string, mixed>}|null */
    public function handle(Account $account): ?array
    {
        $row = DB::table('sessions')->whereIn('character_id', $account->characters()->select('characters.id'))->where('counted', true)->orderByDesc('end_at')->first(['character_id', 'session_start']);
        if ($row === null) {
            return null;
        }
        $character = $account->characters()->whereKey((int) $row->character_id)->first();
        $recap = $character === null ? null : $this->recap->handle($character, (int) $row->session_start, true);

        return $character === null || $recap === null ? null : ['name' => (string) $character->name, 'class' => (string) $character->class, 'recap' => $recap];
    }
}
