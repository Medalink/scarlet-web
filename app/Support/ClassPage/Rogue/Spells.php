<?php

namespace App\Support\ClassPage\Rogue;

use App\Support\ClassRows\SpellBook;
use App\Support\ClassRows\SpellTotals;

/** The rogue spells the page reads, each folded over its ranks by the client's name. */
final readonly class Spells
{
    /** The finishers in the order of their series, and the colour of each. */
    public const array FINISHERS = [
        'Eviscerate' => '#fff468',
        'Slice and Dice' => '#e0a33c',
        'Kidney Shot' => '#d9694a',
        'Rupture' => '#7ecbd6',
        'Expose Armor' => '#a8a8b8',
    ];

    public const array OPENERS = ['Cheap Shot', 'Ambush', 'Garrote', 'Sap'];

    public const array ATTACKS = ['Sinister Strike', 'Backstab', 'Hemorrhage', 'Ghostly Strike', 'Gouge', 'Feint'];

    public const array ESCAPES = ['Sprint', 'Evasion', 'Blind', 'Gouge', 'Vanish'];

    public const array SCOUTING = ['Distract', 'Detect Traps', 'Disarm Trap'];

    public const string STEALTH = 'Stealth';

    /**
     * @param  list<SpellTotals>  $finishers  in the order of `FINISHERS`
     */
    public function __construct(
        public SpellTotals $pickPocket,
        public SpellTotals $pickLock,
        public SpellTotals $vanish,
        public SpellTotals $kick,
        public SpellTotals $sap,
        public SpellTotals $stealth,
        public array $finishers,
    ) {}

    public static function of(SpellBook $book): self
    {
        return new self(
            $book->sum('Pick Pocket', 'Pick Pocket'),
            $book->sum('Pick Lock', 'Pick Lock'),
            $book->sum('Vanish', 'Vanish'),
            $book->sum('Kick', 'Kick'),
            $book->sum('Sap', 'Sap'),
            $book->sum('Stealth', self::STEALTH),
            array_map(fn (string $name): SpellTotals => $book->sum($name, $name), array_keys(self::FINISHERS)),
        );
    }
}
