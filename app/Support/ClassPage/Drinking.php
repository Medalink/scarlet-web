<?php

namespace App\Support\ClassPage;

use App\Support\ClassRows\ClassRows;
use App\Support\StateKind;

/**
 * Time spent under the Drink aura as a share of played time: the figure the warlock's Life Tap and the mage's mana
 * modules both compare with the class and with every class (`class_standings`, figure `FIGURE`). Every class's page
 * stores it, whatever its builder draws (`App\Actions\Feats\StoreClassStandings`).
 */
final class Drinking
{
    public const string AURA = 'Drink';

    public const string FIGURE = 'drink';

    private const string LABEL = 'Time spent drinking';

    /** Percent of played time, null when no time was played or the character shares no aura rows at all, which is not the same as never drinking. */
    public static function measure(ClassRows $rows): ?float
    {
        $played = $rows->playedMs();
        $states = $rows->states(StateKind::OwnBuff, StateKind::OthersBuff);
        if ($played === 0 || ($states->of(StateKind::OwnBuff) === [] && $states->of(StateKind::OthersBuff) === [])) {
            return null;
        }
        $drinking = $states->sum(StateKind::OwnBuff, self::AURA)->ms + $states->sum(StateKind::OthersBuff, self::AURA)->ms;

        return min(100.0, $drinking / $played * 100);
    }

    /** Percent of played time, 0 when `measure()` has none. */
    public static function share(ClassRows $rows): float
    {
        return self::measure($rows) ?? 0.0;
    }

    /** The figure a page stores to compare with the class, or null when `measure()` has none. */
    public static function figure(ClassRows $rows): ?StandingFigure
    {
        $share = self::measure($rows);

        return $share === null ? null : new StandingFigure(self::FIGURE, self::LABEL, $share, Unit::PercentTenth, self::icon($rows));
    }

    /** The icon of the Drink aura the character spent the most time under, or null when it never drank. */
    public static function icon(ClassRows $rows): ?Icon
    {
        $states = $rows->states(StateKind::OwnBuff, StateKind::OthersBuff);
        $drinks = [...$states->of(StateKind::OwnBuff, self::AURA), ...$states->of(StateKind::OthersBuff, self::AURA)];

        return $drinks === [] ? null : Icon::spell($drinks[0]->refId);
    }
}
