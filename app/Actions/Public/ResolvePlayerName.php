<?php

namespace App\Actions\Public;

use App\Models\Character;
use Illuminate\Support\Facades\DB;

class ResolvePlayerName
{
    /** What a page says for a player whose name it may not show. */
    public const string UNKNOWN = 'a player';

    /**
     * The names to show for other players, by pseudonym. A name exists only for a pseudonym the character's Social
     * upload explained (the `pseudonyms` table). The character's owner sees it; a visitor sees it only when that
     * player's own character is public. Every other pseudonym is missing from the answer, so the caller shows `UNKNOWN`.
     *
     * @param  list<string>  $pseudonyms
     * @return array<string, string> pseudonym => display name
     */
    public function handle(int $characterId, bool $owner, array $pseudonyms): array
    {
        if ($pseudonyms === []) {
            return [];
        }
        $known = DB::table('pseudonyms')->where('character_id', $characterId)->whereIn('pseudonym', $pseudonyms)->pluck('name', 'pseudonym');
        if ($owner) {
            return array_map(fn (mixed $name): string => (string) $name, $known->all());
        }

        $public = Character::query()->whereIn('name_key', $known->map(fn (mixed $name): string => Character::keyFor((string) $name))->values()->all())
            ->where('is_public', true)->pluck('name_key')->all();

        return array_map(fn (mixed $name): string => (string) $name, $known->filter(fn (mixed $name): bool => in_array(Character::keyFor((string) $name), $public, true))->all());
    }
}
