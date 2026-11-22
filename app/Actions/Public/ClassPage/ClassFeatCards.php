<?php

namespace App\Actions\Public\ClassPage;

use App\Actions\Feats\ShowCharacterFeats;
use App\Support\ClassPage\ClassRead;
use App\Support\ClassPage\FeatCard;
use App\Support\ClassPage\Icon;
use App\Support\FeatMeasure;
use App\Support\FeatRules;
use App\View\Pages\CharacterFeatWall;

class ClassFeatCards
{
    /** The feats of the class and of the talent tree of the latest build, each at most this many. */
    public const int PER_SCOPE = 4;

    public function __construct(private readonly ShowCharacterFeats $feats) {}

    /**
     * The character's four class feats and four feats of the tree its latest build has most points in, in the order
     * the feat wall on its page lists them, each with the icon of the spell it counts when the character cast one.
     *
     * @return list<FeatCard>
     */
    public function handle(ClassRead $read): array
    {
        $class = $tree = [];
        foreach ($this->feats->handle($read->character, false) as $feat) {
            $scope = FeatRules::RULES[$feat['key']][0] ?? null;
            if ($scope === $read->class && count($class) < self::PER_SCOPE) {
                $class[] = $feat;
            }
            if ($read->facts->tree !== null && $scope === $read->facts->tree && count($tree) < self::PER_SCOPE) {
                $tree[] = $feat;
            }
        }

        return array_map(fn (array $feat): FeatCard => $this->card($read, $feat), [...$class, ...$tree]);
    }

    /** @param  array{key: string, name: string, value: int|float, tier: int, tier_name: string|null, threshold: int|null, next: int|null, pinned: bool}  $feat */
    private function card(ClassRead $read, array $feat): FeatCard
    {
        $card = CharacterFeatWall::card($feat);
        [, $measure, $patterns] = FeatRules::RULES[$feat['key']];
        $spell = $measure === FeatMeasure::Casts ? ($read->rows->spells()->named(...$patterns)[0] ?? null) : null;

        return new FeatCard($card['name'], $card['tier'], $card['detail'], $card['track'], $card['icon'], $spell === null ? null : Icon::spell($spell->iconId));
    }
}
