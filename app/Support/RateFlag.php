<?php

namespace App\Support;

/** The fixed tags that qualify a rate (docs/LOOT.md, Statistical treatment, Tags): their API value is the case value. */
enum RateFlag: string
{
    case OneCharacter = 'one_character';
    case SkinsCounted = 'skins_counted';
    case QuestItem = 'quest_item';

    /**
     * The tags of a row: its windows came from one character, its source also drops leather or hide so skinning is
     * counted as looting, its item is a quest item.
     *
     * @return list<self>
     */
    public static function of(int|float $windowCharacters, bool $skins, bool $quest): array
    {
        return array_values(array_filter([
            (int) round($windowCharacters) === 1 ? self::OneCharacter : null,
            $skins ? self::SkinsCounted : null,
            $quest ? self::QuestItem : null,
        ]));
    }

    /**
     * The tags of a row once skinning is read apart from looting: its windows came from one character, its item is a
     * quest item.
     *
     * @return list<self>
     */
    public static function tags(int|float $windowCharacters, bool $quest): array
    {
        return self::of($windowCharacters, false, $quest);
    }

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
