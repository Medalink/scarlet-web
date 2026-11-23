<?php

namespace App\View\Sc;

use App\View\Format;

/** One compare strip: a hairline axis, the 10th to 90th percentile, the middle half, the median and the viewer's diamond. */
final class Compare
{
    public const int MIN_GROUP = 20;

    /**
     * @param  array{lo: float|int, hi: float|int, p10: float|int, p25: float|int, p50: float|int, p75: float|int, p90: float|int, you: float|int, youText?: string}  $spec
     */
    public static function html(string $name, array $spec): string
    {
        $span = max(1e-9, $spec['hi'] - $spec['lo']);
        $at = fn (float|int $value): float => round(max(0, min(100, ($value - $spec['lo']) / $span * 100)), 2);
        $wide = $at($spec['p90']) - $at($spec['p10']);
        $mid = $at($spec['p75']) - $at($spec['p25']);
        $tip = e($name.': '.($spec['youText'] ?? self::number($spec['you'])).'|Middle half '.self::number($spec['p25']).' to '.self::number($spec['p75']).' · median '.self::number($spec['p50']));

        return '<span class="sc-cmp" data-tip="'.$tip.'"><span class="axis"></span>'
            .'<span class="wide" style="left:'.$at($spec['p10']).'%;width:'.$wide.'%"></span>'
            .'<span class="mid" style="left:'.$at($spec['p25']).'%;width:'.$mid.'%"></span>'
            .'<span class="med" style="left:'.$at($spec['p50']).'%"></span>'
            .'<span class="you" style="left:'.$at($spec['you']).'%"></span></span>';
    }

    public static function number(float|int $value): string
    {
        return $value >= 10 ? Format::number($value) : rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
