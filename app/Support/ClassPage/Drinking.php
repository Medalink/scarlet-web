<?php

namespace App\Support\ClassPage;

use App\Support\ClassRows\ClassRows;
use App\Support\StateKind;

/**
 * Time spent under the Drink aura as a share of played time: the figure the warlock's Life Tap and the mage's mana
 * modules both compare with the class and with every class (`class_standings`, figure `FIGURE`).
 */
final class Drinking
{
    public const string AURA = 'Drink';

    public const string FIGURE = 'drink';

    /** Percent of played time, 0 when no time was played. */
    public static function share(ClassRows $rows): float
    {
        $played = $rows->playedMs();
        $drinking = $rows->states()->sum(StateKind::OwnBuff, self::AURA)->ms + $rows->states()->sum(StateKind::OthersBuff, self::AURA)->ms;

        return $played === 0 ? 0.0 : min(100.0, $drinking / $played * 100);
    }

    /** The icon of the Drink aura the character spent the most time under, or null when it never drank. */
    public static function icon(ClassRows $rows): ?Icon
    {
        $states = [...$rows->states()->of(StateKind::OwnBuff, self::AURA), ...$rows->states()->of(StateKind::OthersBuff, self::AURA)];

        return $states === [] ? null : Icon::spell($states[0]->refId);
    }
}
