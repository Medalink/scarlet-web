<?php

namespace App\View\Sc;

final class Nav
{
    /** Header links in order: key => [label, path]. */
    public const array LINKS = [
        'stats' => ['Stats', '/stats'],
        'feats' => ['Feats', '/feats'],
        'leaderboards' => ['Leaderboards', '/leaderboards'],
        'map' => ['Map', '/map'],
        'loot' => ['Loot', '/loot'],
        'professions' => ['Professions', '/professions'],
        'talents' => ['Talents', '/talents'],
        'camps' => ['Camps', '/camps'],
    ];
}
