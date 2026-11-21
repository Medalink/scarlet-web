<?php

namespace App\View\Design;

use App\View\Format;

/** The data of docs/comps/world.html. */
final class WorldPage
{
    /**
     * @return array<string, mixed>
     */
    public static function data(): array
    {
        $zones = [['Saltmarsh Reach', 3912], ['Hollowmere', 3105], ['The Cinder Flats', 2480], ['Thornwall', 1766], ['Marshlight Fen', 804]];
        $shares = Gallery::shares(array_column($zones, 1));

        return [
            'map' => Heat::map(
                8,
                [[3600, 4800, 900, 620], [5600, 4200, 700, 420], [7200, 3300, 650, 340], [4700, 2600, 500, 200], [6400, 5300, 520, 180], [2900, 7900, 800, 260], [7600, 7200, 900, 300], [8600, 4800, 600, 160]],
                [[3900, 4500], [5200, 4400], [7100, 3000], [6900, 3600], [4500, 2700], [7700, 7000]],
                ['x0' => 600, 'y0' => 200, 'x1' => 9900, 'y1' => 9300],
            ),
            'controls' => [
                ['label' => 'Layer', 'buttons' => true, 'items' => [['label' => 'Paths', 'on' => true], ['label' => 'Deaths'], ['label' => 'Kills']]],
                ['label' => 'Look', 'buttons' => true, 'items' => [['label' => 'Survey', 'on' => true], ['label' => 'Game']]],
                ['label' => 'Ruleset', 'items' => Gallery::filters()['rulesets']],
            ],
            'line' => Format::line('Saltmarsh Reach', ', under the pointer: '.Format::number(811).' characters, '.Format::number(3912).' hours, '.Format::number(6480).' deaths.'),
            'zones' => array_map(fn (array $zone, float $share, int $i): array => [
                'label' => $zone[0], 'href' => Gallery::url('zone'), 'share' => $share,
                'value' => Format::number($zone[1]).($i === 0 ? ' hours' : ''),
            ], $zones, $shares, array_keys($zones)),
        ];
    }
}
