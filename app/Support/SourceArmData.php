<?php

namespace App\Support;

/** The rows behind the arms of one source (App\Actions\Rollups\SourceRuleArms), as App\Actions\Rollups\SourceArmRows read them. */
final readonly class SourceArmData
{
    /**
     * @param  list<array{loot_rule: string, size: int|null, character: int, keyed: bool, day: string, windows: int}>  $windows  `day` is `''` outside free for all
     * @param  list<array<string, mixed>>  $items  item_id, item, bind_type, loot_rule, group_size, keyed, day, drops
     * @param  array<int, array<int, array<string, array<int, int>>>>  $readers  windows with no corpse key that listed the item, by item, size, day and character, free for all
     * @param  list<string>  $midnights  the days whose midnight with the day before has a free-for-all window within 300 s of it (`App\Actions\Rollups\MidnightWindows`)
     */
    public function __construct(
        public array $windows = [],
        public array $items = [],
        public array $readers = [],
        public array $midnights = [],
    ) {}
}
