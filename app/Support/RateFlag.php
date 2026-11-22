<?php

namespace App\Support;

/** The fixed tags that qualify a rate (docs/LOOT.md, Statistical treatment, Tags): their API value is the case value. */
enum RateFlag: string
{
    case OneCharacter = 'one_character';
    case SkinsCounted = 'skins_counted';
    case QuestItem = 'quest_item';

    /** The words a page draws beside the count the tag qualifies. */
    public function label(): string
    {
        return match ($this) {
            self::OneCharacter => '1 character',
            self::SkinsCounted => 'skins counted',
            self::QuestItem => 'quest item',
        };
    }
}
