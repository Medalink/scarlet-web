<?php

namespace App\Support;

/** The rows behind the arms of one source (App\Actions\Rollups\SourceRuleArms), as App\Actions\Rollups\SourceArmRows read them. */
final readonly class SourceArmData
{
    /**
     * @param  list<array{loot_rule: string, size: int|null, character: int, keyed: bool, windows: int}>  $windows
     * @param  list<array<string, mixed>>  $items  item_id, item, bind_type, loot_rule, group_size, keyed, drops
     * @param  array<int, array<int, array<int, int>>>  $readers  windows with no corpse key that listed the item, by item, size and character, free for all
     */
    public function __construct(
        public array $windows = [],
        public array $items = [],
        public array $readers = [],
    ) {}
}
