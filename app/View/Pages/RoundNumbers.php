<?php

namespace App\View\Pages;

use App\View\Format;

/**
 * A count on a survey rule that ends at the next round number (docs/DESIGN.md, "Art and game layer").
 *
 * @phpstan-type Rule array{icon: string, label: string, value: string, at: string, left: string, target: string}
 */
final class RoundNumbers
{
    /**
     * One rule row for a count, or null when the count rounds to less than 1.
     *
     * @return Rule|null
     */
    public static function rule(string $icon, string $label, int|float|null $value, string $suffix = ''): ?array
    {
        $count = $value === null ? 0 : (int) round($value);
        if ($count < 1) {
            return null;
        }
        $target = self::next($count);

        return [
            'icon' => $icon, 'label' => $label, 'value' => Format::number($count).$suffix,
            'at' => number_format($count / $target * 100, 1, '.', ''),
            'left' => Format::number($target - $count).$suffix.' to go', 'target' => Format::number($target).$suffix,
        ];
    }

    /** The smallest of 10, 25, 50, 100, 250, 500, 1,000, 2,500 and so on that is greater than the value. */
    public static function next(int $value): int
    {
        for ($scale = 10; ; $scale *= 10) {
            foreach ([$scale, intdiv($scale * 5, 2), $scale * 5] as $target) {
                if ($target > $value) {
                    return $target;
                }
            }
        }
    }
}
