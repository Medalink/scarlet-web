<?php

namespace App\Support\ClassPage\Priest;

use App\Support\ClassRows\SpellBook;
use App\Support\ClassRows\SpellTotals;

/** The priest spells the page reads, each folded over its ranks by the client's name. */
final readonly class Spells
{
    /** The heals in the order of their series, and the colour of each. */
    public const array HEALS = [
        'Lesser Heal' => '#6b7280',
        'Heal' => '#c9a45a',
        'Flash Heal' => '#f0f1f3',
        'Greater Heal' => '#7cb7ff',
        'Renew' => '#6fd1a0',
        'Power Word: Shield' => '#f2d06b',
        'Prayer of Healing' => '#d58cff',
    ];

    public const array SHADOW = ['Smite', 'Shadow Word: Pain', 'Mind Blast', 'Holy Fire', 'Mind Flay', 'Devouring Plague'];

    public const array CONTROL = ['Fade', 'Psychic Scream', 'Shackle Undead', 'Mind Soothe', 'Mind Control'];

    public const array BUFFS = ['Power Word: Fortitude', 'Inner Fire', 'Shadow Protection', 'Divine Spirit', 'Levitate'];

    public const array DISPELS = ['Dispel Magic', 'Cure Disease', 'Abolish Disease'];

    public const string SHADOWFORM = 'Shadowform';

    public const string WAND = 'Shoot';

    public const string CANDLE = 'Holy Candle';

    /** @param  list<SpellTotals>  $heals  in the order of `HEALS` */
    public function __construct(
        public SpellTotals $shield,
        public SpellTotals $resurrection,
        public SpellTotals $fortitude,
        public SpellTotals $prayer,
        public array $heals,
    ) {}

    public static function of(SpellBook $book): self
    {
        return new self(
            $book->sum('Power Word: Shield', 'Power Word: Shield'),
            $book->sum('Resurrection', 'Resurrection'),
            $book->sum('Power Word: Fortitude', 'Power Word: Fortitude'),
            $book->sum('Prayer of Fortitude', 'Prayer of Fortitude'),
            array_map(fn (string $name): SpellTotals => $book->sum($name, $name), array_keys(self::HEALS)),
        );
    }

    /** @return list<string> the colour of each heal, in the order of `HEALS` */
    public static function hues(): array
    {
        return array_values(self::HEALS);
    }

    public function fortitudes(): int
    {
        return $this->fortitude->casts + $this->prayer->casts;
    }
}
