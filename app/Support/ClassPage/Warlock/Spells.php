<?php

namespace App\Support\ClassPage\Warlock;

use App\Support\ClassPage\Icon;
use App\Support\ClassRows\SpellBook;
use App\Support\ClassRows\SpellTotals;

/** The warlock spells the page reads, each folded over its ranks by the client's name. */
final readonly class Spells
{
    /** The summons that bring a demon out, by name (the names of `App\Support\FeatRules` for the demon caller). */
    public const array SUMMONS = ['Summon Imp', 'Summon Voidwalker', 'Summon Succubus', 'Summon Felhunter', 'Summon Felguard', 'Inferno', 'Ritual of Doom'];

    public function __construct(
        public SpellTotals $lifeTap,
        public SpellTotals $drainSoul,
        public SpellTotals $shadowburn,
        public SpellTotals $soulFire,
        public SpellTotals $healthstones,
        public SpellTotals $soulstones,
        public SpellTotals $ritual,
        public SpellTotals $summons,
        public SpellTotals $funnel,
        public SpellTotals $sacrifice,
        public SpellTotals $fear,
        public SpellTotals $shadowBolt,
        public SpellTotals $siphon,
        public SpellTotals $felDomination,
        public SpellTotals $conflagrate,
        public SpellTotals $soulLink,
    ) {}

    public static function of(SpellBook $book): self
    {
        return new self(
            $book->sum('Life Tap', 'Life Tap'),
            $book->sum('Drain Soul', 'Drain Soul'),
            $book->sum('Shadowburn', 'Shadowburn'),
            $book->sum('Soul Fire', 'Soul Fire'),
            $book->sum('Healthstones', 'Create Healthstone*'),
            $book->sum('Soulstones', 'Create Soulstone*'),
            $book->sum('Ritual of Summoning', 'Ritual of Summoning'),
            $book->sum('Summoning a demon', ...self::SUMMONS),
            $book->sum('Health Funnel', 'Health Funnel'),
            $book->sum('Demonic Sacrifice', 'Demonic Sacrifice'),
            $book->sum('Fear', 'Fear'),
            $book->sum('Shadow Bolt', 'Shadow Bolt'),
            $book->sum('Siphon Life', 'Siphon Life'),
            $book->sum('Fel Domination', 'Fel Domination'),
            $book->sum('Conflagrate', 'Conflagrate'),
            $book->sum('Soul Link', 'Soul Link'),
        );
    }

    /** The icon of a spell total's most cast rank, or null when it was never cast. */
    public static function icon(SpellTotals $spell): ?Icon
    {
        return $spell->iconId === 0 ? null : Icon::spell($spell->iconId);
    }
}
