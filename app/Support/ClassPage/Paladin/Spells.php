<?php

namespace App\Support\ClassPage\Paladin;

use App\Support\ClassPage\Icon;
use App\Support\ClassRows\SpellBook;
use App\Support\ClassRows\SpellTotals;

/** The paladin spells the page reads, each folded over its ranks by the client's name. */
final readonly class Spells
{
    public const array SEALS = ['Seal of Righteousness', 'Seal of Command', 'Seal of the Crusader', 'Seal of Light', 'Seal of Wisdom', 'Seal of Justice'];

    public const array BLESSINGS = ['Blessing of Might', 'Blessing of Wisdom', 'Blessing of Kings', 'Blessing of Salvation', 'Blessing of Light', 'Blessing of Sanctuary', 'Blessing of Protection', 'Blessing of Freedom', 'Blessing of Sacrifice'];

    public const array GREATER = ['Greater Blessing of Might', 'Greater Blessing of Wisdom', 'Greater Blessing of Kings', 'Greater Blessing of Salvation', 'Greater Blessing of Light', 'Greater Blessing of Sanctuary'];

    public const array UNDEAD = ['Exorcism', 'Holy Wrath', 'Turn Undead', 'Consecration', 'Hammer of Wrath', 'Holy Shield'];

    public const array HEALS = ['Holy Light', 'Flash of Light', 'Holy Shock'];

    public const array CONTROL = ['Hammer of Justice', 'Repentance', 'Turn Undead'];

    public const array SAVES = ['Blessing of Protection', 'Divine Intervention', 'Redemption'];

    public function __construct(
        public SpellTotals $judgement,
        public SpellTotals $blessings,
        public SpellTotals $greater,
        public SpellTotals $divineShield,
        public SpellTotals $divineProtection,
        public SpellTotals $layOnHands,
        public SpellTotals $hammer,
        public SpellTotals $redemption,
        public SpellTotals $repentance,
        public SpellTotals $consecration,
        public SpellTotals $divineFavor,
        public SpellTotals $holyShock,
        public SpellTotals $kings,
        public SpellTotals $mount,
        public SpellBook $book,
    ) {}

    public static function of(SpellBook $book): self
    {
        return new self(
            $book->sum('Judgements', 'Judgement'),
            $book->sum('Blessings', ...self::BLESSINGS),
            $book->sum('Greater blessings', ...self::GREATER),
            $book->sum('Divine Shield', 'Divine Shield'),
            $book->sum('Divine Protection', 'Divine Protection'),
            $book->sum('Lay on Hands', 'Lay on Hands'),
            $book->sum('Hammer of Justice', 'Hammer of Justice'),
            $book->sum('Redemption', 'Redemption'),
            $book->sum('Repentance', 'Repentance'),
            $book->sum('Consecration', 'Consecration'),
            $book->sum('Divine Favor', 'Divine Favor'),
            $book->sum('Holy Shock', 'Holy Shock'),
            $book->sum('Blessing of Kings', 'Blessing of Kings', 'Greater Blessing of Kings'),
            $book->sum('Summoning a mount', 'Summon Warhorse', 'Summon Charger'),
            $book,
        );
    }

    /** The icon of a spell total's most cast rank, or null when it was never cast. */
    public static function icon(SpellTotals $spell): ?Icon
    {
        return $spell->iconId === 0 ? null : Icon::spell($spell->iconId);
    }

    /** All the blessings cast, single and greater. */
    public function blessingCasts(): int
    {
        return $this->blessings->casts + $this->greater->casts;
    }
}
