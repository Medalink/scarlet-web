<?php

namespace App\Actions\Upload;

use App\Models\Character;
use App\Models\Install;
use App\Support\Exceptions\ApiException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The name-holding rule (docs/DESIGN.md, Security): the first install to upload a name holds it, and
 * another install may upload the same name only when both belong to one account.
 */
class ClaimCharacter
{
    /**
     * @param  bool  $create  false answers null for a name nobody uploaded yet, true makes the character
     */
    public function handle(Install $install, string $name, bool $create): ?Character
    {
        $character = Character::query()->where('name_key', Character::keyFor($name))->first();

        if ($character === null) {
            return $create ? $this->create($install, $name) : null;
        }

        if ($this->sent($install, $character)) {
            return $character;
        }
        if (! $this->sameAccount($install, $character)) {
            throw ApiException::nameHeld($character->name);
        }
        if ($create) {
            $this->attach($install, $character);
        }

        return $character;
    }

    private function sent(Install $install, Character $character): bool
    {
        return DB::table('install_character')->where('install_id', $install->id)->where('character_id', $character->id)->exists();
    }

    private function sameAccount(Install $install, Character $character): bool
    {
        if ($install->account_id === null) {
            return false;
        }

        return $character->account_id === $install->account_id
            || $character->firstInstall?->account_id === $install->account_id;
    }

    private function create(Install $install, string $name): Character
    {
        $this->assertRoom($install);

        try {
            return DB::transaction(function () use ($install, $name): Character {
                $character = Character::query()->create([
                    'name' => $name,
                    'name_key' => Character::keyFor($name),
                    'first_install_id' => $install->id,
                    'account_id' => $install->account_id,
                    // Public from the first upload; gold and trades stay behind show_economy.
                    'is_public' => true,
                ]);
                $this->attach($install, $character);

                return $character;
            });
        } catch (UniqueConstraintViolationException) {
            // Another request made the character first: the rule applies to it.
            return $this->handle($install, $name, create: false) ?? throw ApiException::nameHeld($name);
        }
    }

    private function attach(Install $install, Character $character): void
    {
        DB::table('install_character')->insertOrIgnore(['install_id' => $install->id, 'character_id' => $character->id, 'created_at' => now()]);
    }

    private function assertRoom(Install $install): void
    {
        $limit = (int) config('scarlet.upload.max_characters_per_install');
        if (DB::table('install_character')->where('install_id', $install->id)->count() >= $limit) {
            throw ApiException::tooManyCharacters($limit);
        }
    }
}
