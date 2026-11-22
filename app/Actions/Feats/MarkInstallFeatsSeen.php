<?php

namespace App\Actions\Feats;

use App\Models\Character;
use App\Models\Install;
use App\Support\Exceptions\ApiException;

class MarkInstallFeatsSeen
{
    public function __construct(private readonly MarkFeatsSeen $mark) {}

    /**
     * Record the feat tiers the app showed for one character of the install.
     *
     * @param  array<array-key, int>  $shown  feat key => the tier that was shown
     *
     * @throws ApiException 404 when the character is not the install's
     */
    public function handle(Install $install, string $name, array $shown): void
    {
        $character = Character::query()->ownedBy($install)->where('characters.name_key', Character::keyFor($name))->first()
            ?? throw ApiException::notFound('The character');

        $this->mark->handle($character, $shown);
    }
}
