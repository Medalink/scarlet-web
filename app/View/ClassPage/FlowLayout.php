<?php

namespace App\View\ClassPage;

use App\Support\ClassPage\Flow;
use App\Support\ClassPage\Icon;
use App\Support\ClassPage\Unit;

/**
 * The geometry of a flow: ribbons from the sources on the left into one pool and out to the uses on the right, drawn in
 * an SVG 900 units wide. Returns null for a flow with nothing in its pool.
 *
 * @phpstan-type Pin array{ref: Icon, x: string, y: string, dim: bool}
 * @phpstan-type Side array{ribbon: string, tone: string, x: string, y: string, w: string, h: string, icon: Pin|null, label: string, value: string, tx: string, ty: string, vy: string}
 */
final class FlowLayout
{
    private const int WIDTH = 900;

    private const int POOL_HEIGHT = 300;

    private const int LEFT_X = 204;

    private const int POOL_X = 445;

    private const int RIGHT_X = 686;

    private const int NODE = 10;

    private const int LEFT_GAP = 18;

    private const int RIGHT_GAP = 5;

    private const int SLOT_MIN = 26;

    private const int ICON = 20;

    private const int TOP = 40;

    /**
     * @return array{width: int, height: string, total: string, poolX: int, poolY: string, left: list<Side>, right: list<Side>}|null
     */
    public static function of(Flow $flow): ?array
    {
        $total = array_sum(array_map(fn ($item) => max(0, $item->count), $flow->left));
        $rightSum = array_sum(array_map(fn ($item) => max(0, $item->count), $flow->right));
        if ($total <= 0 || $flow->left === []) {
            return null;
        }
        $scale = self::POOL_HEIGHT / $total;
        $rightScale = $rightSum > 0 ? $total / $rightSum : 0;
        $leftHeights = array_map(fn ($item) => max(0, $item->count) * $scale, $flow->left);
        $rightHeights = array_map(fn ($item) => max(0, $item->count) * $rightScale * $scale, $flow->right);
        $slots = array_map(fn (float|int $h) => max($h, self::SLOT_MIN), $rightHeights);
        $leftHeight = array_sum($leftHeights) + self::LEFT_GAP * (count($leftHeights) - 1);
        $rightHeight = array_sum($slots) + self::RIGHT_GAP * max(0, count($slots) - 1);
        $body = max(self::POOL_HEIGHT, $leftHeight, $rightHeight);
        $poolTop = self::TOP + ($body - self::POOL_HEIGHT) / 2;

        $left = [];
        $y = self::TOP + ($body - $leftHeight) / 2;
        $t = $poolTop;
        foreach ($flow->left as $i => $item) {
            $h = $leftHeights[$i];
            $mid = $y + $h / 2;
            $left[] = [
                'ribbon' => self::ribbon(self::LEFT_X + self::NODE, $y, $y + $h, self::POOL_X, $t, $t + $h),
                'tone' => $item->tone->value,
                'x' => (string) self::LEFT_X, 'y' => self::f($y), 'w' => (string) self::NODE, 'h' => self::f(max($h, 1.6)),
                'icon' => self::pin($item->icon, self::LEFT_X - 12 - self::ICON, $mid, false),
                'label' => $item->label,
                'value' => Unit::Count->say($item->count).' · '.Unit::Count->say(round($item->count / $total * 100)).'%',
                'tx' => (string) (self::LEFT_X - 20 - self::ICON), 'ty' => self::f($mid - 2), 'vy' => self::f($mid + 14),
            ];
            $y += $h + self::LEFT_GAP;
            $t += $h;
        }

        $right = [];
        $y = self::TOP + ($body - $rightHeight) / 2;
        $t = $poolTop;
        foreach ($flow->right as $i => $item) {
            $h = $rightHeights[$i];
            $a = $y + ($slots[$i] - $h) / 2;
            $drawn = max($h, 1.6);
            $mid = $y + $slots[$i] / 2;
            $right[] = [
                'ribbon' => self::ribbon(self::POOL_X + self::NODE, $t, $t + $h, self::RIGHT_X, $a, $a + $drawn),
                'tone' => $item->tone->value,
                'x' => (string) self::RIGHT_X, 'y' => self::f($a), 'w' => (string) self::NODE, 'h' => self::f($drawn),
                'icon' => self::pin($item->icon, self::RIGHT_X + self::NODE + 10, $mid, $item->tone->value === 'lost'),
                'label' => $item->label,
                'value' => Unit::Count->say($item->count),
                'tx' => (string) (self::RIGHT_X + self::NODE + 18 + self::ICON), 'ty' => self::f($mid + 4.5), 'vy' => self::f($mid + 4.5),
            ];
            $y += $slots[$i] + self::RIGHT_GAP;
            $t += $h;
        }

        return [
            'width' => self::WIDTH,
            'height' => self::f(self::TOP + $body + 12),
            'total' => Unit::Count->say($total),
            'poolX' => self::POOL_X,
            'poolY' => self::f($poolTop),
            'left' => $left,
            'right' => $right,
        ];
    }

    /** @return Pin|null */
    private static function pin(?Icon $icon, float|int $x, float|int $mid, bool $dim): ?array
    {
        if ($icon === null) {
            return null;
        }

        return ['ref' => $icon, 'x' => self::f($x), 'y' => self::f($mid - self::ICON / 2), 'dim' => $dim];
    }

    private static function ribbon(float|int $x0, float|int $a0, float|int $b0, float|int $x1, float|int $a1, float|int $b1): string
    {
        $m = ($x0 + $x1) / 2;

        return 'M'.$x0.' '.self::f($a0).'C'.$m.' '.self::f($a0).' '.$m.' '.self::f($a1).' '.$x1.' '.self::f($a1)
            .'L'.$x1.' '.self::f($b1).'C'.$m.' '.self::f($b1).' '.$m.' '.self::f($b0).' '.$x0.' '.self::f($b0).'Z';
    }

    private static function f(float|int $value): string
    {
        return number_format($value, 1, '.', '');
    }
}
