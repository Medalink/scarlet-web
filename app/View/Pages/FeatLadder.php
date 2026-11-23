<?php

namespace App\View\Pages;

use App\Support\Feat;
use App\Support\FeatText;
use App\View\Sc\Crest;

/** The ladder of a feat: five rows from Scarlet to Uncommon, each with the count it takes and the share of characters at or above it, and a line at the viewer's count. */
final class FeatLadder
{
    /**
     * @param  list<int>  $thresholds  uncommon to scarlet
     * @param  list<float>|null  $shares  the percent at each tier or higher, copper first
     * @param  int|null  $held  the tier the viewer holds, null to draw no line
     * @param  string|null  $you  the words on the line: "Sable, 9,912 kills"
     * @return list<array<string, int|string>>
     */
    public static function rows(Feat $feat, array $thresholds, ?array $shares, ?int $held, ?string $you): array
    {
        $rows = [];
        for ($tier = count(Feat::TIERS); $tier >= 1; $tier--) {
            if ($you !== null && $held === $tier) {
                $rows[] = ['you' => $you];
            }
            $share = $shares[$tier - 1] ?? null;
            $rows[] = [
                'tier' => $tier, 'name' => Crest::TIER_NAMES[$tier], 'share' => $share === null ? '–' : FeatsPage::percent($share),
                'width' => $share === null ? 0 : max(1, (int) round($share)), 'at' => FeatText::counted($feat, $thresholds[$tier - 1]),
            ];
        }
        if ($you !== null && $held === 0) {
            $rows[] = ['you' => $you];
        }

        return $rows;
    }
}
