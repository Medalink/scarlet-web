<?php

namespace App\View\Sc;

final class Nav
{
    /** Header links in order: key => [label, path]. The paths are today's; stage 8 moves them to the new URLs. */
    public const array LINKS = [
        'stats' => ['Stats', '/stats'],
        'feats' => ['Feats', '/feats'],
        'leaderboards' => ['Leaderboards', '/leaderboards'],
        'map' => ['Map', '/map'],
        'loot' => ['Loot', '/loot'],
        'professions' => ['Professions', '/professions/fishing'],
        'talents' => ['Talents', '/talents'],
        'camps' => ['Camps', '/camps'],
    ];
}
