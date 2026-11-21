<?php

namespace App\View\Pages;

/**
 * The order of the modules of the character page under the records and the week: one row per list entry, each entry a
 * module of resources/views/pages/character/ with its width on the 12-column sheet (null is a third) and the level of
 * its heading. A character at the level cap leads with what it did there and moves the levelling under its own heading.
 */
final class CharacterLayout
{
    /** The module that draws the heading of the levelling section. */
    public const string LEVELLING = 'levelling';

    /**
     * @return list<list<array{module: string, span: string|null, level: int}>>
     */
    public static function rows(bool $capped, bool $gold): array
    {
        if (! $capped) {
            return [
                [self::m('level-times', $gold ? 'w8' : 'w12'), ...($gold ? [self::m('gold')] : [])],
                [self::m('xp', 'w8'), self::m('zones')],
                [self::m('deaths', 'w12')],
                [self::m('items', 'w8'), self::m('skills')],
                [self::m('killed'), self::m('runs', 'w8')],
                [self::m('days', 'w8'), self::m('crafted')],
                [self::m('hours', 'w12')],
                [self::m('history', 'w12')],
            ];
        }

        return [
            [self::m('killed'), self::m('runs', 'w8')],
            [self::m('deaths', 'w12')],
            [self::m('items', 'w8'), self::m('skills')],
            [self::m('crafted', 'w5'), self::m('zones', 'w7')],
            [self::m('days', 'w12')],
            $gold ? [self::m('hours', 'w8'), self::m('gold')] : [self::m('hours', 'w12')],
            [self::m(self::LEVELLING, 'w12')],
            [self::m('level-times', 'w12', 3)],
            [self::m('xp', 'w12', 3)],
            [self::m('history', 'w12')],
        ];
    }

    /** @return array{module: string, span: string|null, level: int} */
    private static function m(string $module, ?string $span = null, int $level = 2): array
    {
        return ['module' => $module, 'span' => $span, 'level' => $level];
    }
}
