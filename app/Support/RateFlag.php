<?php

namespace App\Support;

/** The fixed tags that qualify a rate (docs/LOOT.md, Statistical treatment, Tags): their API value is the case value. */
enum RateFlag: string
{
    case OneCharacter = 'one_character';
    case QuestItem = 'quest_item';

    /**
     * The tags of a row: its windows came from one character, its item is a quest item.
     *
     * @return list<self>
     */
    public static function tags(int|float $windowCharacters, bool $quest): array
    {
        return array_values(array_filter([
            (int) round($windowCharacters) === 1 ? self::OneCharacter : null,
            $quest ? self::QuestItem : null,
        ]));
    }

    /** The words a page draws beside the count the tag qualifies. */
    public function label(): string
    {
        return match ($this) {
            self::OneCharacter => '1 character',
            self::QuestItem => 'quest item',
        };
    }
}
