<?php

namespace App\Support;

/** The scene a page's head draws, one per profession page, one per class page and one for Camps: the art it draws is on the `maps` disk under a folder of this name (tools/scene-art). */
enum Scene: string
{
    case Fishing = 'fishing';
    case Herbalism = 'herbalism';
    case Mining = 'mining';
    case Skinning = 'skinning';
    case Alchemy = 'alchemy';
    case Blacksmithing = 'blacksmithing';
    case Enchanting = 'enchanting';
    case Engineering = 'engineering';
    case Leatherworking = 'leatherworking';
    case Tailoring = 'tailoring';
    case Cooking = 'cooking';
    case FirstAid = 'first-aid';
    case Camp = 'camp';
    case Warrior = 'warrior';
    case Paladin = 'paladin';
    case Hunter = 'hunter';
    case Rogue = 'rogue';
    case Priest = 'priest';
    case Shaman = 'shaman';
    case Mage = 'mage';
    case Warlock = 'warlock';
    case Druid = 'druid';

    /** What a screen reader hears of the stage. */
    public function label(): string
    {
        return match ($this) {
            self::Fishing => "A fishing spot at night, drawn from the game's own models: a bobber on the water beside a school of fish, with a tackle box, a pole and a rack of fish on the bank",
            self::Herbalism => "A meadow at first light, drawn from the game's own models: Peacebloom, Silverleaf, Mageroyal, Kingsblood and other herbs growing where a player would pick them",
            self::Mining => "A mine floor lit by a forge, drawn from the game's own models: copper, tin, iron and mithril veins, a smelting forge, a mine cart, a lantern and a mining pick",
            self::Skinning => "A skinner's corner at dusk, drawn from the game's own models: two tanning racks, stacks of hides, a leather bag and a lantern",
            self::Alchemy => "An alchemist's corner, drawn from the game's own models: two alchemy sets, a cauldron over a fire, and a table with a bubbling bowl and bottles",
            self::Blacksmithing => "A smithy, drawn from the game's own models: an anvil with a hammer on it, a forge, a furnace, a grindstone, a weapon rack and a bench of metal bars",
            self::Enchanting => "An enchanter's study, drawn from the game's own models: a lectern, a column of light, stacks of books, candelabras and candles",
            self::Engineering => "An engineer's workshop, drawn from the game's own models: an autolathe, two gnomish machines, a toolbox, a wrench, a powder keg, dynamite and a mortar",
            self::Leatherworking => "A leatherworker's yard, drawn from the game's own models: a tanning rack, a bench with boots on it, hides, two saddles, a leather bag and a lantern",
            self::Tailoring => "A tailor's room, drawn from the game's own models: a loom, a spinning wheel, a table of cloth and thread, and bolts of cloth",
            self::Cooking => "A cook's fire, drawn from the game's own models: a cooking pot over a fire, a boar on a spit, a rack of smoked meat, a table of food and a sack",
            self::FirstAid => "A field hospital, drawn from the game's own models: a tent, a bed, first aid crates and a lantern",
            self::Camp => "A camp at night, drawn from the game's own models: a Basic Campfire burning between a Camp Tent and a Camp Chair",
            self::Warrior => "A training yard at dusk, drawn from the game's own models: a target dummy, a weapon rack and an armor stand beside a brazier",
            self::Paladin => "A cathedral yard at dawn, drawn from the game's own models: a warhorse beside a lectern, with a shield rack and a banner",
            self::Hunter => "A clearing at first light, drawn from the game's own models: a campfire with a cat, a wolf, a boar and an owl around it",
            self::Rogue => "A back alley at night, drawn from the game's own models: a lockbox, crates and a barrel, a rack of poison vials, a coin sack, a pile of gold and a lantern",
            self::Priest => "A chapel corner by candlelight, drawn from the game's own models: a lectern, an altar, a candelabra, candles and a pew",
            self::Shaman => "Open ground under a storm sky, drawn from the game's own models: a fire, an earth, a water and an air totem standing in an arc",
            self::Mage => "A tower floor at night, drawn from the game's own models: a portal, a sheep, a table of bottles and a bookshelf",
            self::Warlock => "A ritual circle at night, drawn from the game's own models: an Imp, a Voidwalker, a Succubus and a Felhunter standing on it",
            self::Druid => "A moonlit grove, drawn from the game's own models: a bear, a cat, a travel form and an aquatic form",
        };
    }

    /** The line under the head that names what Blizzard made and what the page drew. */
    public function credit(): string
    {
        return match ($this) {
            self::Fishing => 'Bobber, fish school, tackle box, pole and fish rack models, textures, ripples and splashes © Blizzard Entertainment, read from the WoW Forever client. The water, the bank and the moonlight are drawn by this page.',
            self::Herbalism => self::onDrawnGround('Herb models and textures'),
            self::Mining => self::onDrawnGround('Vein, forge, mine cart, lantern and mining pick models, textures and sparkles'),
            self::Skinning => self::onDrawnGround('Tanning rack, hide, leather bag and lantern models and textures'),
            self::Alchemy => self::onDrawnGround('Alchemy set, cauldron, table, bowl, bottle and book models, textures and effects'),
            self::Blacksmithing => self::onDrawnGround('Anvil, hammer, forge, furnace, grindstone, weapon rack, bench and metal bar models, textures and effects'),
            self::Enchanting => self::onDrawnGround('Lectern, light, book, candelabra and candle models, textures and effects'),
            self::Engineering => self::onDrawnGround('Autolathe, machine, toolbox, wrench, powder keg, dynamite and mortar models, textures and effects'),
            self::Leatherworking => self::onDrawnGround('Tanning rack, bench, boot, hide, saddle, leather bag and lantern models, textures and effects'),
            self::Tailoring => self::onDrawnGround('Loom, spinning wheel, table, bolt of cloth and spool models, textures and effects'),
            self::Cooking => self::onDrawnGround('Cooking pot, spit, smoked meat rack, table, food and sack models, textures and effects'),
            self::FirstAid => self::onDrawnGround('Tent, bed, first aid crate and lantern models, textures and effects'),
            self::Camp => 'Campfire, tent and chair models, textures, flames, sparks and light © Blizzard Entertainment, read from the WoW Forever client. The smoke and the ground are drawn by this page.',
            self::Warrior => self::onDrawnGround('Target dummy, weapon rack, armor stand and brazier models, textures and flames'),
            self::Paladin => self::onDrawnGround('Warhorse, lectern, shield rack and banner models and textures'),
            self::Hunter => self::onDrawnGround('Cat, wolf, boar, owl and campfire models, textures and flames'),
            self::Rogue => self::onDrawnGround('Lockbox, crate, barrel, poison vial, coin sack, gold and lantern models, textures and effects'),
            self::Priest => self::onDrawnGround('Lectern, altar, candelabra, candle and pew models, textures and flames'),
            self::Shaman => self::onDrawnGround('Fire, earth, water and air totem models, textures and effects'),
            self::Mage => self::onDrawnGround('Portal, sheep, alchemy table and bookshelf models, textures and effects'),
            self::Warlock => self::onDrawnGround('Ritual circle, Imp, Voidwalker, Succubus and Felhunter models, textures and effects'),
            self::Druid => self::onDrawnGround('Bear, cat, travel form and aquatic form models and textures'),
        };
    }

    /** The class whose page draws this scene, null for a profession page or the camps. */
    public function playerClass(): ?PlayerClass
    {
        return PlayerClass::tryFrom(strtoupper($this->value));
    }

    private static function onDrawnGround(string $art): string
    {
        return $art.' © Blizzard Entertainment, read from the WoW Forever client. The ground and the light are drawn by this page.';
    }
}
