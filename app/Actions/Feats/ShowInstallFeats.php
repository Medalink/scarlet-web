<?php

namespace App\Actions\Feats;

use App\Models\Character;
use App\Models\Install;
use App\View\Pages\CharacterFeatWall;

class ShowInstallFeats
{
    public function __construct(
        private readonly ShowCharacterFeats $feats,
        private readonly ShowNewFeats $new,
    ) {}

    /**
     * The feats of every character the install owns that has stored feats, most recently seen first, as the desktop app
     * shows them: the feat wall with the economy shown, and the feats its owner has not been shown yet.
     *
     * @return list<array<string, mixed>>
     */
    public function handle(Install $install): array
    {
        $characters = Character::query()->ownedBy($install)
            ->whereExists(fn ($rows) => $rows->selectRaw('1')->from('character_feats')->whereColumn('character_feats.character_id', 'characters.id'))
            ->orderByRaw('characters.last_seen_at desc nulls last')->orderBy('characters.id')
            ->get();

        $entries = [];
        foreach ($characters as $character) {
            $section = CharacterFeatWall::section($this->feats->handle($character, true), $character);
            if ($section !== null) {
                $entries[] = ['name' => $character->name] + $section + ['new' => $this->new->handle($character)];
            }
        }

        return $entries;
    }
}
