<?php

namespace App\Support\ClassPage;

/**
 * The parts of a class page that only the class knows: what its builder returns. The page adds the character line, the
 * links, the talent modules the build holds, the comparison from the stored standings and the feats.
 */
final readonly class ClassParts
{
    /**
     * @param  list<Callout>  $callouts
     * @param  list<Figure>  $figures  six, under the head
     * @param  list<Figure>  $band  five, on the character page
     * @param  list<Row>  $sheet  the first row holds the class's own chart
     * @param  list<TalentModule>  $talents  one for each talent in `ClassFacts::$talents`, in its order
     * @param  list<Row>  $party
     * @param  list<StandingFigure>  $standings  the six compared with the class
     */
    public function __construct(
        public ?Icon $icon,
        public array $callouts,
        public array $figures,
        public array $band,
        public array $sheet,
        public array $talents,
        public array $party,
        public array $standings,
    ) {}
}
