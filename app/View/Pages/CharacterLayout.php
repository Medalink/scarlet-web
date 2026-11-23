<?php

namespace App\View\Pages;

/**
 * The chapters of the character page under the records and the week. A chapter is a heading, a lead module at the full
 * width of the 12-column sheet, and the rest packed into rows of 12, the last row stretched to fill it. A character with
 * an hour at the level cap leads with what it did there and moves the levelling chapter down.
 */
final class CharacterLayout
{
    private const int FULL = 12;

    /**
     * @param  bool  $steps  the skills module leads with its step lines and takes eight columns
     * @param  bool  $talents  the character has a stored talent build, which gets a chapter after combat
     * @param  bool  $levels  the character has a Levels figure; without one, quests lead the levelling chapter or it is left out
     * @param  bool  $lifetime  the character has a stored lifetime read or an instance lock, which gets a chapter before play time
     * @return list<array{id: string, title: string, rows: list<list<array{module: string, span: string|null, level: int}>>}>
     */
    public static function chapters(bool $capped, bool $gold, bool $quests, bool $creatures, bool $steps, bool $talents, bool $levels, bool $lifetime): array
    {
        $levelling = match (true) {
            $levels => self::chapter('Levelling', 'levelling', 'levels', $quests ? [['quests', 8]] : []),
            $quests => self::chapter('Levelling', 'levelling', 'quests', []),
            default => null,
        };
        $combat = self::chapter('Combat', 'combat', 'deaths', [['killed', 4], ['runs', 8], ...($creatures ? [['creatures', 4]] : [])]);
        $loot = self::chapter('Loot and skills', 'loot', 'items', [['skills', $steps ? 8 : 4], ...($gold ? [['gold', 4]] : [])]);
        $build = self::chapter('Talents', 'talents', 'talents', [['respecs', 4], ['talent-order', 12], ['talent-facts', 12]]);
        $time = self::chapter('Play time', 'time', 'hours', [['distance', 12], ['zones', 12], ['history', 12]]);

        $baselines = $lifetime ? self::chapter('Lifetime', 'lifetime', 'lifetime', []) : null;

        $chapters = $capped ? [$combat, $loot, $baselines, $time, $levelling] : [$levelling, $combat, $loot, $baselines, $time];
        if ($talents) {
            array_splice($chapters, 2, 0, [$build]);
        }

        return array_values(array_filter($chapters));
    }

    /**
     * @param  list<array{0: string, 1: int}>  $rest  the modules after the lead with their width in columns
     * @return array{id: string, title: string, rows: list<list<array{module: string, span: string|null, level: int}>>}
     */
    private static function chapter(string $title, string $id, string $lead, array $rest): array
    {
        $rows = [[['module' => $lead, 'span' => 'w12', 'level' => 3]]];
        $row = [];
        $used = 0;
        foreach ($rest as [$module, $width]) {
            if ($used + $width > self::FULL) {
                $rows[] = self::filled($row, self::FULL - $used);
                $row = [];
                $used = 0;
            }
            $row[] = ['module' => $module, 'width' => $width];
            $used += $width;
        }
        if ($row !== []) {
            $rows[] = self::filled($row, self::FULL - $used);
        }

        return ['id' => 'h-ch-'.$id, 'title' => $title, 'rows' => $rows];
    }

    /**
     * @param  list<array{module: string, width: int}>  $row
     * @param  int  $spare  the columns of the row no module takes, which the last module takes
     * @return list<array{module: string, span: string|null, level: int}>
     */
    private static function filled(array $row, int $spare): array
    {
        $last = count($row) - 1;
        $modules = [];
        foreach ($row as $i => $m) {
            $modules[] = ['module' => $m['module'], 'span' => match ($m['width'] + ($i === $last ? $spare : 0)) {
                4 => null,
                8 => 'w8',
                default => 'w12',
            }, 'level' => 3];
        }

        return $modules;
    }
}
