<?php

namespace App\View\Sc;

final class Spark
{
    /**
     * The sparkline of a tile: a 2 px line with an end dot, or columns with the newest in scarlet.
     *
     * @param  list<int|float>  $values
     */
    public static function svg(array $values, string $form, int $w, int $h, string $color): string
    {
        $max = max(1, ...$values);
        $count = count($values);
        $out = '<svg viewBox="0 0 '.$w.' '.$h.'" width="'.$w.'" height="'.$h.'" class="sc-spark" role="img">';
        if ($form === 'cols') {
            $bar = ($w - ($count - 1) * 2) / $count;
            foreach ($values as $i => $value) {
                $tall = max(1, $value / $max * $h);
                $fill = $i === $count - 1 ? $color : ($value > 0 ? 'rgba(255,255,255,0.3)' : 'rgba(255,255,255,0.1)');
                $out .= '<rect x="'.round($i * ($bar + 2), 2).'" y="'.round($h - $tall, 2).'" width="'.round($bar, 2).'" height="'.round($tall, 2).'" fill="'.$fill.'"/>';
            }

            return $out.'</svg>';
        }
        $points = [];
        foreach ($values as $i => $value) {
            $points[] = [round($i / max(1, $count - 1) * ($w - 6) + 3, 2), round($h - 3 - $value / $max * ($h - 6), 2)];
        }
        $line = implode(' ', array_map(fn (array $p): string => $p[0].','.$p[1], $points));
        $last = $points[$count - 1];

        return $out.'<polyline points="'.$line.'" fill="none" stroke="'.$color.'" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/><circle cx="'.$last[0].'" cy="'.$last[1].'" r="3" fill="'.$color.'" stroke="var(--surface, #141416)" stroke-width="2"/></svg>';
    }
}
