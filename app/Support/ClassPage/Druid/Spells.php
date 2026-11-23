<?php

namespace App\Support\ClassPage\Druid;

use App\Support\ClassPage\Icon;
use App\Support\ClassRows\SpellBook;
use App\Support\ClassRows\SpellTotals;

/** The druid spells the page reads, each folded over its ranks by the client's name. */
final readonly class Spells
{
    public const array BEAR = ['Maul', 'Swipe', 'Growl', 'Demoralizing Roar', 'Bash'];

    public const array CAT = ['Claw', 'Shred', 'Rake', 'Rip', 'Ferocious Bite', 'Ravage'];

    public const array CASTER = ['Wrath', 'Moonfire', 'Starfire', 'Healing Touch', 'Regrowth', 'Rejuvenation'];

    public const array CONTROL = ['Faerie Fire', 'Entangling Roots', "Nature's Grasp", 'Hibernate', 'Soothe Animal'];

    public const array WILD = ['Teleport: Moonglade', 'Mark of the Wild', 'Thorns', 'Remove Curse', 'Abolish Poison', 'Cure Poison'];

    /** The patterns of a spell that the client words in more than one name. */
    private const array NAMES = [
        'Faerie Fire' => ['Faerie Fire', 'Faerie Fire (*)'],
        'Mark of the Wild' => ['Mark of the Wild', 'Gift of the Wild'],
        'Feral Charge' => ['Feral Charge', 'Feral Charge (*)'],
    ];

    public function __construct(
        private SpellBook $book,
        public SpellTotals $innervate,
        public SpellTotals $rebirth,
        public SpellTotals $dash,
        public SpellTotals $prowl,
        public SpellTotals $moonglade,
        public SpellTotals $feralCharge,
        public SpellTotals $naturesGrasp,
        public SpellTotals $naturesSwiftness,
        public SpellTotals $swiftmend,
    ) {}

    public static function of(SpellBook $book): self
    {
        return new self(
            $book,
            $book->sum('Innervate', 'Innervate'),
            $book->sum('Rebirth', 'Rebirth'),
            $book->sum('Dash', 'Dash'),
            $book->sum('Prowl', 'Prowl'),
            $book->sum('Teleport: Moonglade', 'Teleport: Moonglade'),
            $book->sum('Feral Charge', ...self::NAMES['Feral Charge']),
            $book->sum("Nature's Grasp", "Nature's Grasp"),
            $book->sum("Nature's Swiftness", "Nature's Swiftness"),
            $book->sum('Swiftmend', 'Swiftmend'),
        );
    }

    /**
     * The spells of a list that were cast, the most cast first.
     *
     * @param  list<string>  $names
     * @return list<SpellTotals>
     */
    public function cast(array $names): array
    {
        $cast = [];
        foreach ($names as $name) {
            $spell = $this->book->sum($name, ...(self::NAMES[$name] ?? [$name]));
            if ($spell->casts > 0) {
                $cast[] = $spell;
            }
        }
        usort($cast, fn (SpellTotals $a, SpellTotals $b): int => [$b->casts, $a->name] <=> [$a->casts, $b->name]);

        return $cast;
    }

    /** The icon of a spell total's most cast rank, or null when it was never cast. */
    public static function icon(SpellTotals $spell): ?Icon
    {
        return $spell->iconId === 0 ? null : Icon::spell($spell->iconId);
    }
}
