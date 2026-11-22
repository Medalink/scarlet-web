<?php

namespace App\View\ClassPage;

use App\Support\ClassPage\Icon;
use App\Support\ClassPage\Link;
use App\Support\ClassPage\LinkItem;
use App\Support\ClassPage\Tone;
use App\Support\ClassPage\Unit;

/**
 * The geometry of a link chart: ribbons from a left list to a right list with no pool between, in an SVG 900 units
 * wide. A pair that names an item the lists do not have is ignored. Returns null for a chart with no pairs.
 *
 * @phpstan-type Pin array{ref: Icon, x: string, y: string, dim: bool}
 * @phpstan-type Side array{tone: string, x: string, y: string, w: string, h: string, icon: Pin|null, label: string, value: string, tx: string, ty: string, vy: string}
 */
final class LinkLayout
{
    private const int WIDTH = 900;

    private const int BODY_HEIGHT = 300;

    private const int LEFT_X = 204;

    private const int RIGHT_X = 686;

    private const int NODE = 10;

    private const int LEFT_GAP = 14;

    private const int RIGHT_GAP = 10;

    private const int SLOT_MIN = 26;

    private const int ICON = 20;

    private const int TOP = 40;

    /**
     * @return array{width: int, height: string, total: string, totalX: float, ribbons: list<array{d: string, tone: string}>, left: list<Side>, right: list<Side>}|null
     */
    public static function of(Link $link): ?array
    {
        $pairs = [];
        $total = 0.0;
        foreach ($link->pairs as $pair) {
            if (isset($link->left[$pair->left], $link->right[$pair->right]) && $pair->count > 0) {
                $pairs[] = $pair;
                $total += $pair->count;
            }
        }
        if ($pairs === []) {
            return null;
        }
        $leftSums = array_fill(0, count($link->left), 0.0);
        $rightSums = array_fill(0, count($link->right), 0.0);
        foreach ($pairs as $pair) {
            $leftSums[$pair->left] += $pair->count;
            $rightSums[$pair->right] += $pair->count;
        }
        $scale = self::BODY_HEIGHT / $total;
        $leftHeights = array_values(array_map(fn (float $sum) => $sum * $scale, $leftSums));
        $rightHeights = array_values(array_map(fn (float $sum) => $sum * $scale, $rightSums));
        $leftSlots = array_map(fn (float $h) => max($h, self::SLOT_MIN), $leftHeights);
        $rightSlots = array_map(fn (float $h) => max($h, self::SLOT_MIN), $rightHeights);
        $leftTotal = array_sum($leftSlots) + self::LEFT_GAP * (count($leftSlots) - 1);
        $rightTotal = array_sum($rightSlots) + self::RIGHT_GAP * (count($rightSlots) - 1);
        $body = max($leftTotal, $rightTotal);
        $leftTops = self::tops($leftSlots, $leftHeights, self::LEFT_GAP, $leftTotal, $body);
        $rightTops = self::tops($rightSlots, $rightHeights, self::RIGHT_GAP, $rightTotal, $body);

        $leftAt = $leftTops;
        $rightAt = $rightTops;
        $fromLeft = $fromRight = [];
        $order = array_keys($pairs);
        usort($order, fn (int $a, int $b) => [$pairs[$a]->left, $pairs[$a]->right] <=> [$pairs[$b]->left, $pairs[$b]->right]);
        foreach ($order as $i) {
            $fromLeft[$i] = $leftAt[$pairs[$i]->left];
            $leftAt[$pairs[$i]->left] += $pairs[$i]->count * $scale;
        }
        usort($order, fn (int $a, int $b) => [$pairs[$a]->right, $pairs[$a]->left] <=> [$pairs[$b]->right, $pairs[$b]->left]);
        foreach ($order as $i) {
            $fromRight[$i] = $rightAt[$pairs[$i]->right];
            $rightAt[$pairs[$i]->right] += $pairs[$i]->count * $scale;
        }

        $ribbons = [];
        $m = (self::LEFT_X + self::NODE + self::RIGHT_X) / 2;
        foreach ($pairs as $i => $pair) {
            $h = $pair->count * $scale;
            $y0 = $fromLeft[$i];
            $y1 = $fromRight[$i];
            $x0 = self::LEFT_X + self::NODE;
            $ribbons[] = [
                'd' => 'M'.$x0.' '.self::f($y0).'C'.$m.' '.self::f($y0).' '.$m.' '.self::f($y1).' '.self::RIGHT_X.' '.self::f($y1)
                    .'L'.self::RIGHT_X.' '.self::f($y1 + $h).'C'.$m.' '.self::f($y1 + $h).' '.$m.' '.self::f($y0 + $h).' '.$x0.' '.self::f($y0 + $h).'Z',
                'tone' => $link->right[$pair->right]->tone->value,
            ];
        }

        $left = [];
        foreach ($link->left as $i => $item) {
            $mid = $leftTops[$i] + $leftHeights[$i] / 2;
            $left[] = [
                'tone' => Tone::Own->value,
                'x' => (string) self::LEFT_X, 'y' => self::f($leftTops[$i]), 'w' => (string) self::NODE, 'h' => self::f(max($leftHeights[$i], 1.6)),
                'icon' => self::pin($item, self::LEFT_X - 12 - self::ICON, $mid, false),
                'label' => $item->label,
                'value' => self::share($leftSums[$i], $total),
                'tx' => (string) (self::LEFT_X - 20 - self::ICON), 'ty' => self::f($mid - 2), 'vy' => self::f($mid + 14),
            ];
        }
        $right = [];
        foreach ($link->right as $i => $item) {
            $mid = $rightTops[$i] + $rightHeights[$i] / 2;
            $right[] = [
                'tone' => $item->tone->value,
                'x' => (string) self::RIGHT_X, 'y' => self::f($rightTops[$i]), 'w' => (string) self::NODE, 'h' => self::f(max($rightHeights[$i], 1.6)),
                'icon' => self::pin($item, self::RIGHT_X + self::NODE + 10, $mid, $item->tone === Tone::Lost),
                'label' => $item->label,
                'value' => self::share($rightSums[$i], $total),
                'tx' => (string) (self::RIGHT_X + self::NODE + 18 + self::ICON), 'ty' => self::f($mid + 4.5), 'vy' => self::f($mid + 4.5),
            ];
        }

        return [
            'width' => self::WIDTH,
            'height' => self::f(self::TOP + $body + 12),
            'total' => Unit::Count->say($total),
            'totalX' => (self::LEFT_X + self::RIGHT_X + self::NODE) / 2.0,
            'ribbons' => $ribbons,
            'left' => $left,
            'right' => $right,
        ];
    }

    /**
     * @param  list<float>  $slots
     * @param  list<float>  $heights
     * @return list<float>
     */
    private static function tops(array $slots, array $heights, int $gap, float $all, float $body): array
    {
        $y = self::TOP + ($body - $all) / 2;
        $tops = [];
        foreach ($slots as $i => $slot) {
            $tops[] = $y + ($slot - $heights[$i]) / 2;
            $y += $slot + $gap;
        }

        return $tops;
    }

    /** @return array{ref: Icon, x: string, y: string, dim: bool}|null */
    private static function pin(LinkItem $item, float|int $x, float|int $mid, bool $dim): ?array
    {
        if ($item->icon === null) {
            return null;
        }

        return ['ref' => $item->icon, 'x' => self::f($x), 'y' => self::f($mid - self::ICON / 2), 'dim' => $dim];
    }

    private static function share(float $sum, float $total): string
    {
        return Unit::Count->say($sum).' · '.Unit::Count->say(round($sum / $total * 100)).'%';
    }

    private static function f(float|int $value): string
    {
        return number_format($value, 1, '.', '');
    }
}
