<?php

namespace App\Support;

/** The feats the catalog dropped, with the feat each one merged into (null: cut) and why; the catalog lists them under `retired`. */
final class FeatRetired
{
    /** @var list<array{0: string, 1: string, 2: string|null, 3: string}> key, name, merged into, why */
    public const array LIST = [
        ['regular', 'Regular', 'Devoted', 'Counts logins, which a relog inflates. Devoted counts the days'],
        ['longstanding', 'Longstanding', 'Seasoned', 'Rises without playing. Seasoned counts the weeks with play'],
        ['full-house', 'Full House', 'Delver', 'Nearly every dungeon run is a group of five, so it counted Delver\'s runs again'],
        ['scout', 'Scout', null, 'Elite creatures stand beside every road; the count rose without the player doing anything'],
        ['witness', 'Witness', 'Vanquisher', 'Vanquisher and Conqueror count the bosses killed; seeing one adds nothing'],
        ['scavenger', 'Scavenger', null, 'Grey items are a by-product of Looter. Lucky, Rare Find and Jackpot cover the drops worth noticing'],
        ['practised', 'Practised', 'Artisan', 'A skill-up is a rank gained, which Artisan already sums'],
        ['battle-medic', 'Battle Medic', 'Slayer', 'Kills are not what a healer is for, and Slayer counts them'],
        ['frontliner', 'Frontliner', 'Slayer', 'Slayer counts the same kills'],
        ['first-to-fall', 'First to Fall', 'Fallen', 'Fallen counts the same deaths'],
        ['skirmisher', 'Skirmisher', 'Delver', 'Every Rogue, Mage, Warlock and Hunter session is in this role, so it repeated the general feat'],
        ['headhunter', 'Headhunter', 'Vanquisher', 'Every Rogue, Mage, Warlock and Hunter session is in this role, so it repeated the general feat'],
        ['warbringer', 'Warbringer', 'Conqueror', 'Every Rogue, Mage, Warlock and Hunter session is in this role, so it repeated the general feat'],
        ['untouched', 'Untouched', 'Flawless', 'Every Rogue, Mage, Warlock and Hunter session is in this role, so it repeated the general feat'],
        ['cleaver', 'Cleaver', 'Slayer', 'Every Rogue, Mage, Warlock and Hunter session is in this role, so it repeated the general feat'],
        ['duelist', 'Duelist', 'Gladiator', 'Every Rogue, Mage, Warlock and Hunter session is in this role, so it repeated the general feat'],
        ['backliner', 'Backliner', 'Delver', 'Every Rogue, Mage, Warlock and Hunter session is in this role, so it repeated the general feat'],
        ['spellslinger', 'Spellslinger', 'Vanquisher', 'Every Rogue, Mage, Warlock and Hunter session is in this role, so it repeated the general feat'],
        ['siegebreaker', 'Siegebreaker', 'Conqueror', 'Every Rogue, Mage, Warlock and Hunter session is in this role, so it repeated the general feat'],
        ['out-of-reach', 'Out of Reach', 'Flawless', 'Every Rogue, Mage, Warlock and Hunter session is in this role, so it repeated the general feat'],
        ['sniper', 'Sniper', 'Slayer', 'Every Rogue, Mage, Warlock and Hunter session is in this role, so it repeated the general feat'],
        ['bombardier', 'Bombardier', 'Gladiator', 'Every Rogue, Mage, Warlock and Hunter session is in this role, so it repeated the general feat'],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_column(self::LIST, 0);
    }
}
