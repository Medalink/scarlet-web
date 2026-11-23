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
        private readonly ShowFeatCardFields $fields,
        private readonly ShowFeatBasis $basis,
    ) {}

    /**
     * The feats of every character the install owns that has stored feats, most recently seen first, as the desktop app
     * shows them: the feat wall with the economy shown, the feat score, the spec and the newest session counted, a card
     * with the fields of `ShowFeatCardFields`, and the feats its owner has not been shown yet.
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
            $rows = $this->feats->handle($character, true);
            $section = CharacterFeatWall::section($rows, $character);
            if ($section === null) {
                continue;
            }
            $fields = $this->fields->handle($character, $rows);
            $with = fn (array $cards): array => array_map(fn (array $card): array => $card + $fields[$card['key']], $cards);
            $entries[] = [
                'name' => $character->name,
                'held' => $section['held'], 'total' => $section['total'], 'tiers' => $section['tiers'],
                'since' => $section['since'], 'limit' => $section['limit'],
                'score' => (int) $character->feat_score,
                ...$this->basis->handle($character),
                'pinned' => $with($section['pinned']), 'top' => $with($section['top']),
                'earned' => $with($section['earned']), 'unearned' => $with($section['unearned']),
                'new' => $this->new->handle($character),
            ];
        }

        return $entries;
    }
}
