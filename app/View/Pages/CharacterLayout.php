<?php

namespace App\View\Pages;

/**
 * The order of the modules of the character page under the records and the week: one row per list entry, each entry a
 * module of resources/views/pages/character/ with its width on the 12-column sheet (null is a third) and the level of
 * its heading. A character at the level cap leads with what it did there and moves the levelling under its own heading.
 * The levelling modules (time in each level, experience by level) are left out when the character has nothing in them:
 * one first seen at the cap has no levelling to show.
 */
final class CharacterLayout
{
    /** The module that draws the heading of the levelling section. */
    public const string LEVELLING = 'levelling';

    /**
     * @return list<list<array{module: string, span: string|null, level: int}>>
     */
    public static function rows(bool $capped, bool $gold, bool $talents, bool $levelTimes = true, bool $xp = true): array
    {
        $build = $talents ? [[self::m('talents', 'w8'), self::m('respecs')], [self::m('talent-order', 'w12')], [self::m('talent-facts', 'w12')]] : [];

        if (! $capped) {
            return array_values(array_filter([
                $levelTimes || $gold ? [...($levelTimes ? [self::m('level-times', $gold ? 'w8' : 'w12')] : []), ...($gold ? [self::m('gold', $levelTimes ? null : 'w12')] : [])] : null,
                $xp ? [self::m('xp', 'w8'), self::m('zones')] : [self::m('zones', 'w12')],
                [self::m('deaths', 'w12')],
                [self::m('items', 'w8'), self::m('skills')],
                [self::m('killed'), self::m('runs', 'w8')],
                [self::m('days'), self::m('hours', 'w8')],
                [self::m('crafted', 'w12')],
                ...$build,
                [self::m('history', 'w12')],
            ]));
        }

        return array_values(array_filter([
            [self::m('killed'), self::m('runs', 'w8')],
            [self::m('deaths', 'w12')],
            [self::m('items', 'w8'), self::m('skills')],
            [self::m('crafted', 'w5'), self::m('zones', 'w7')],
            [self::m('days'), self::m('hours', 'w8')],
            ...($gold ? [[self::m('gold', 'w12')]] : []),
            ...$build,
            $levelTimes || $xp ? [self::m(self::LEVELLING, 'w12')] : null,
            $levelTimes ? [self::m('level-times', 'w12', 3)] : null,
            $xp ? [self::m('xp', 'w12', 3)] : null,
            [self::m('history', 'w12')],
        ]));
    }

    /** @return array{module: string, span: string|null, level: int} */
    private static function m(string $module, ?string $span = null, int $level = 2): array
    {
        return ['module' => $module, 'span' => $span, 'level' => $level];
    }
}
