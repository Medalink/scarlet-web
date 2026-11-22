<?php

namespace App\Support;

/** What a campsite spell is: the decoder's table of them (part/src/camp.rs) names the same kinds. */
enum CampSpellKind: string
{
    case Near = 'near';
    case Sit = 'sit';
    case Tent = 'tent';
    case Benefits = 'benefits';
    case Benefit = 'benefit';
    case Campfire = 'campfire';
    case PlacedTent = 'placed_tent';
    case PlacedChair = 'placed_chair';
}
