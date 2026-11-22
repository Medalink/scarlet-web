<?php

namespace App\Support;

/**
 * The `kind` of a `state_times` row (docs/ROWS.md) and the sharing group whose lines it is read from. A removed group
 * deletes only its own kinds.
 */
enum StateKind: string
{
    case OwnBuff = 'a';
    case OthersBuff = 'b';
    case Debuff = 'x';
    case AutoRepeat = 'r';
    case Totem = 't';
    case Fight = 'f';
    case Mount = 'm';
    case Tracking = 'k';
    case Pet = 'p';
    case PetHappiness = 'h';
    case MainHand = 'w';
    case OffHand = 'o';

    public function group(): string
    {
        return match ($this) {
            self::OwnBuff, self::OthersBuff, self::Debuff, self::AutoRepeat, self::Totem, self::Fight => SharingGroup::COMBAT,
            self::Mount, self::Tracking => SharingGroup::MOVEMENT,
            self::Pet, self::PetHappiness, self::MainHand, self::OffHand => SharingGroup::BUILD,
        };
    }

    /**
     * The kinds read from the lines of a sharing group.
     *
     * @return list<string>
     */
    public static function of(string $group): array
    {
        return array_values(array_map(
            fn (self $kind): string => $kind->value,
            array_filter(self::cases(), fn (self $kind): bool => $kind->group() === $group),
        ));
    }
}
