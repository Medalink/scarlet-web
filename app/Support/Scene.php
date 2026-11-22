<?php

namespace App\Support;

/** A profession page's scene: the art it draws is on the `maps` disk under a folder of this name (tools/scene-art). */
enum Scene: string
{
    case Fishing = 'fishing';
    case Herbalism = 'herbalism';
    case Mining = 'mining';
    case Skinning = 'skinning';
}
