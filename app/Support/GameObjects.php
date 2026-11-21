<?php

namespace App\Support;

/** Game objects by id, as `loot_opens.source_id` carries them for a source of kind `g` (docs/ROWS.md). */
final class GameObjects
{
    /** The bobber of a fishing line: every fish caught is looted from it. */
    public const int FISHING_BOBBER = 35591;
}
