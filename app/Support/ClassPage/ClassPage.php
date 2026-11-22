<?php

namespace App\Support\ClassPage;

use App\Support\PlayerClass;
use App\View\Format;

/**
 * Everything a class page draws, in page order. The slices that read the database fill this and the view draws it; no
 * component reads anything else. A list left empty is a section the page does not draw, and so is a null comparison.
 */
final readonly class ClassPage
{
    /**
     * @param  list<Callout>  $callouts  on the props of the head's scene, which is named by the class's slug
     * @param  list<Figure>  $figures  six
     * @param  list<Row>  $sheet  the first row holds the class's own chart
     * @param  list<TalentModule>  $talents  up to three, each only while the latest build has the talent
     * @param  list<Row>  $party
     * @param  list<FeatCard>  $feats
     */
    public function __construct(
        public PlayerClass $class,
        public CharacterLine $character,
        public Links $links,
        public array $callouts,
        public array $figures,
        public array $sheet,
        public array $talents,
        public array $party,
        public ?Comparison $comparison,
        public array $feats,
    ) {}

    /** `warlock`: the name of the class's scene, the last part of its URL and its icon files. */
    public function slug(): string
    {
        return strtolower($this->class->value);
    }

    /** `Warlock`. */
    public function name(): string
    {
        return Format::className($this->class->value);
    }
}
