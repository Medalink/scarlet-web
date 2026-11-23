<?php

namespace App\View\Design;

use App\Actions\Feats\ExportCatalog;
use App\Support\Feat;
use App\Support\FeatText;
use App\View\Pages\FeatsPage;

/** The answer of ShowFeatsHub for the gallery page /design/feats: a Warlock with invented counts and shares over the real catalog, the way docs/comps/feats-web.html draws one. */
final class FeatsDemo
{
    /** The tier of each draw of the random numbers: none, Uncommon, Rare, Epic, Legendary. */
    private const array CUTS = [0.22, 0.58, 0.85, 0.97];

    /**
     * @return array<string, mixed>
     */
    public static function hub(): array
    {
        $rng = new Rng(52);
        /** @var list<array<string, mixed>> $catalog */
        $catalog = app(ExportCatalog::class)->handle()['feats'];
        $feats = [];
        foreach ($catalog as $feat) {
            if (! self::holds($feat)) {
                continue;
            }
            $shares = self::shares($rng);
            $tier = 0;
            $draw = $rng->next();
            foreach (self::CUTS as $cut) {
                $tier += $draw >= $cut ? 1 : 0;
            }
            $along = $rng->next();
            $feats[] = $feat['key'] === 'slayer' ? self::slayer($feat) : self::feat($feat, $tier, $along, $shares);
        }
        $tiers = [0, 0, 0, 0, 0];
        $score = 0;
        foreach ($feats as $feat) {
            if ($feat['tier'] > 0) {
                $tiers[$feat['tier'] - 1]++;
                $score += Feat::from($feat['key'])->scores() ? $feat['tier'] : 0;
            }
        }
        $open = array_values(array_filter($feats, fn (array $feat): bool => $feat['tier'] < 5 && $feat['value'] > 0));
        usort($open, fn (array $a, array $b): int => [$b['progress'], $a['key']] <=> [$a['progress'], $b['key']]);

        return [
            'mode' => 'compare',
            'names' => ['Sable-Varrow'],
            'character' => [
                'name' => 'Sable-Varrow', 'class' => 'WARLOCK', 'race' => 'Human', 'level' => 60, 'held' => array_sum($tiers), 'total' => count($feats), 'tiers' => $tiers, 'score' => $score,
                'place' => 2140, 'of' => 18420,
                'general' => count(array_filter($feats, fn (array $feat): bool => $feat['scope'] === 'general')),
                'class_feats' => count(array_filter($feats, fn (array $feat): bool => in_array($feat['scope'], ['class', 'tree'], true))),
                'role_feats' => count(array_filter($feats, fn (array $feat): bool => $feat['scope'] === 'role')), 'roles' => ['Ranged damage'],
            ],
            'catalog' => count($catalog),
            'feats' => $feats,
            'closest' => array_slice($open, 0, 3),
            'rarest' => ['key' => 'rampage', 'name' => 'Rampage', 'tier' => 4, 'character' => 'Ilse-Marrow', 'share' => 0.4],
        ];
    }

    /**
     * @param  array<string, mixed>  $feat
     */
    private static function holds(array $feat): bool
    {
        return match ($feat['scope']) {
            'general' => true,
            'role' => in_array('Warlock', self::roleClasses()[$feat['role']] ?? [], true),
            default => ($feat['class'] ?? null) === 'Warlock',
        };
    }

    /** @return array<string, list<string>> */
    private static function roleClasses(): array
    {
        /** @var array<string, list<string>> */
        return app(ExportCatalog::class)->handle()['role_classes'];
    }

    /** @return list<float> the percent at each tier or higher, falling with the tier */
    private static function shares(Rng $rng): array
    {
        $shares = [];
        foreach ([0, 1, 2, 3, 4] as $tier) {
            $shares[] = round(max(0.3, min(98, 96 * (0.42 ** $tier) * (0.6 + 0.8 * $rng->next()))), 1);
        }
        rsort($shares);

        return $shares;
    }

    /**
     * @param  array<string, mixed>  $feat
     * @param  list<float>  $shares
     * @return array<string, mixed>
     */
    private static function feat(array $feat, int $tier, float $along, array $shares): array
    {
        $thresholds = $feat['thresholds'];
        $from = $tier === 0 ? 0 : $thresholds[$tier - 1];
        $next = $thresholds[$tier] ?? null;
        $value = $next === null ? $from : min($next - 1, (int) round($from + ($next - $from) * $along * 0.92));

        return self::fill($feat + ['shares' => $shares], $tier, $value);
    }

    /**
     * @param  array<string, mixed>  $feat
     * @return array<string, mixed>
     */
    private static function slayer(array $feat): array
    {
        return self::fill(['shares' => [93.0, 64.0, 19.0, 5.0, 0.4]] + $feat, 2, 9912);
    }

    /**
     * @param  array<string, mixed>  $feat
     * @return array<string, mixed>
     */
    private static function fill(array $feat, int $tier, int $value): array
    {
        $kind = Feat::from($feat['key']);
        $thresholds = $feat['thresholds'];
        $next = $thresholds[$tier] ?? null;
        $from = $tier === 0 ? 0 : $thresholds[$tier - 1];
        $left = $next === null ? null : max(0, $next - $value);

        return $feat + [
            'tier' => $tier, 'value' => $value, 'next' => $next, 'left' => $left,
            'progress' => $next === null ? 1.0 : round(max(0, min(1, ($value - $from) / ($next - $from))), 3),
            'counted' => FeatText::counted($kind, $value), 'to_next' => $left === null ? null : FeatText::toNext($kind, $left, $tier),
            'spell' => null, 'share' => $feat['shares'][max($tier, 1) - 1],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function data(): array
    {
        return FeatsPage::data(self::hub(), ['scope' => 'compare', 'feat' => 'slayer']);
    }
}
