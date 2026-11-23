<?php

namespace App\Actions\Sessions;

use App\Models\Character;
use Illuminate\Support\Facades\DB;

/** What the page of one session needs: the character, the recap, and the sessions before and after it. */
class ShowSession
{
    private const int MIN_PLAYED_MS = 60_000;

    public function __construct(private readonly BuildRecap $recap) {}

    /**
     * Null when the character or the session is unknown, and when the viewer may not see session pages: they are public when the owner made the character
     * public and kept the sessions switch on, and always shown to the owner. Money is in the recap for the owner, or when the owner shows the economy.
     *
     * @return array{character: Character, recap: array<string, mixed>, owner: bool, economy: bool}|null
     */
    public function handle(string $name, int $start, ?int $viewerAccountId): ?array
    {
        $character = Character::query()->where('name_key', Character::keyFor($name))->first();
        if ($character === null) {
            return null;
        }
        $owner = $viewerAccountId !== null && $character->account_id === $viewerAccountId;
        if (! $owner && ! ($character->is_public && $character->show_sessions)) {
            return null;
        }
        $economy = $owner || $character->show_economy;
        $recap = $this->recap->handle($character, $start, $economy);
        if ($recap === null) {
            return null;
        }
        $recap['links'] = [
            'page' => '/c/'.rawurlencode($character->name).'/sessions/'.$start,
            'earlier' => $this->neighbour($character, $start, false),
            'later' => $this->neighbour($character, $start, true),
        ];

        return ['character' => $character, 'recap' => $recap, 'owner' => $owner, 'economy' => $economy];
    }

    private function neighbour(Character $character, int $start, bool $later): ?int
    {
        $found = DB::table('sessions')->where('character_id', $character->id)->where('session_start', $later ? '>' : '<', $start)->where('played_ms', '>=', self::MIN_PLAYED_MS)
            ->orderBy('session_start', $later ? 'asc' : 'desc')->value('session_start');

        return $found === null ? null : (int) $found;
    }
}
