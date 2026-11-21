<?php

namespace App\Support;

/** The two looks of the world map tiles (docs/DESIGN.md, Maps). The values are the folder names tools/map-tiles writes. */
enum MapLook: string
{
    case Game = 'game';
    case Survey = 'survey';
}
