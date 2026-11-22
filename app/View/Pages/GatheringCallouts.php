<?php

namespace App\View\Pages;

use App\Support\GatherKind;
use App\View\Format;

/** The three callouts on the props of a gathering scene, from the answer of App\Actions\Public\GatheringDashboard. */
final class GatheringCallouts
{
    /**
     * A callout whose name or number the answer lacks is left out; one that only lacks its share is kept without it.
     *
     * @param  array<string, mixed>  $r
     * @return list<array{key: string, title: string, text: string, share: string|null}>
     */
    public static function for(GatherKind $kind, array $r): array
    {
        $t = StatsPage::totals($r['totals']);
        if ($t['characters'] === null) {
            return [];
        }
        $looted = (int) $t['looted'];
        $attempts = (int) $t['attempts'];
        $item = StatsPage::rows($r['items'])[0] ?? null;
        $zone = StatsPage::rows($r['zones'])[0] ?? null;
        $creature = StatsPage::rows($r['creatures'])[0] ?? null;
        $of = fn (mixed $part, int $whole, string $suffix): ?string => ($share = CampsPage::share(min((int) $part, $whole), $whole)) === null ? null : $share.' '.$suffix;
        $itemName = $item === null ? null : (string) ($item['name'] ?? 'Item '.$item['item_id']);
        $zoneName = $zone === null ? null : (string) ($zone['name'] ?? 'Zone '.$zone['zone_id']);
        $seconds = $t['median_seconds'] === null ? null : self::seconds((float) $t['median_seconds']);
        $skillUps = $of($t['skill_ups'], $attempts, 'give a skill point');

        $callouts = match ($kind) {
            GatherKind::Fish => [
                ['bobber', 'Bobber', $seconds === null ? null : 'The wait for a bite is '.$seconds, $of($t['looted'], $attempts, 'of casts catch')],
                ['rack', $itemName, 'The most caught fish', $item === null ? null : $of($item['gathers'], $looted, 'of catches')],
                ['box', 'Clams, crates and trunks', (int) $t['container_catches'] > 0 && $looted > 0 ? 'Catches that open for more' : null, (int) $t['container_catches'] > 0 && $looted > 0 ? '1 catch in '.Format::number(max(1, round($looted / (int) $t['container_catches']))) : null],
            ],
            GatherKind::Herb => [
                ['peacebloom', $itemName, 'The most picked herb', $item === null ? null : $of($item['gathers'], $looted, 'of picks')],
                ['mageroyal', 'A pick', $seconds === null ? null : 'Takes '.$seconds, $skillUps],
                ['silverleaf', $zoneName, 'Where most herbs are picked', $zone === null ? null : $of($zone['looted'], $looted, 'of picks')],
            ],
            GatherKind::Ore => [
                ['copper', $itemName, 'The most mined ore', $item === null ? null : $of($item['gathers'], $looted, 'of strikes')],
                ['mithril', 'A vein', (int) $t['first_strikes'] > 0 ? 'Gives '.Format::number($attempts / (int) $t['first_strikes'], 1).' strikes' : null, $of($t['first_strikes'], $attempts, 'are first strikes')],
                ['forge', $zoneName, 'Where most ore is mined', $zone === null ? null : $of($zone['looted'], $looted, 'of strikes')],
            ],
            GatherKind::Skin => [
                ['rack', $itemName, 'The most skinned leather', $item === null ? null : $of($item['gathers'], $looted, 'of skins')],
                ['hides', $creature === null ? null : (string) ($creature['name'] ?? 'Creature '.$creature['npc_id']), 'The most skinned creature', $creature === null ? null : $of($creature['gathers'], $attempts, 'of skins')],
                ['bag', 'A skin', $seconds === null ? null : 'Takes '.$seconds, $skillUps],
            ],
        };

        $kept = [];
        foreach ($callouts as [$key, $title, $text, $share]) {
            if ($title !== null && $text !== null) {
                $kept[] = ['key' => $key, 'title' => $title, 'text' => $text, 'share' => $share];
            }
        }

        return $kept;
    }

    /** "15 seconds", "3.2 seconds": a whole number from ten seconds up. */
    public static function seconds(float $seconds): string
    {
        $text = $seconds < 10 ? Format::number($seconds, 1) : Format::number($seconds);

        return $text.($text === '1' || $text === '1.0' ? ' second' : ' seconds');
    }
}
