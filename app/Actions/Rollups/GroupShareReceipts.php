<?php

namespace App\Actions\Rollups;

use App\Support\LootRule;
use App\Support\LootSourceKind;
use App\Support\LootVia;
use App\Support\RollOutcome;
use Illuminate\Support\Facades\DB;

class GroupShareReceipts
{
    private const int ROLL_BEFORE_RECEIPT_SECONDS = 90;

    private const int ROLL_AFTER_RECEIPT_SECONDS = 5;

    /**
     * Add to `group_share_receipts` the receipts of the days from `$from` (included) to `$until` (excluded), `-infinity` and
     * `infinity` for all of them: under each rule that owes every member an equal share and at each group size of 2 or
     * more, the items a character received while grouped that were looted (a receipt whose source window is a corpse or an
     * object, which includes a skin read, or a receipt that its own roll won, since a roll win lands after the window
     * closed), by day and character, so a reader sums the days and counts the characters (`GroupShare`). The roll that won a
     * receipt is read from any day: it comes up to 90 s before the receipt and 5 s after, so a range of days cannot cut it
     * from its receipt. The caller deletes the days first (`RebuildKeyedLoot`).
     */
    public function handle(string $from, string $until): void
    {
        DB::statement(<<<'SQL'
            insert into group_share_receipts
            select time_bucket('1 day', d.at), d.loot_rule, d.group_size, d.character_id, count(*)
            from loot_drops d
            where d.counted and d.via = :via and d.group_size >= 2 and d.loot_rule = any(:rules::text[])
                and d.at >= :from::timestamptz and d.at < :until::timestamptz
                and (
                    d.source_kind in (:creature, :object)
                    or exists (
                        select 1 from loot_rolls r
                        where r.character_id = d.character_id and r.session_start = d.session_start and r.item_id = d.item_id
                            and r.counted and r.mine and r.outcome = :won
                            and r.at between d.at - make_interval(secs => :before) and d.at + make_interval(secs => :after)
                    )
                )
            group by 1, 2, 3, 4
            SQL, [
            'via' => LootVia::Receipt->value, 'rules' => '{'.implode(',', LootRule::equalShare()).'}', 'from' => $from, 'until' => $until,
            'creature' => LootSourceKind::Creature->value, 'object' => LootSourceKind::Object->value, 'won' => RollOutcome::Won->value,
            'before' => self::ROLL_BEFORE_RECEIPT_SECONDS, 'after' => self::ROLL_AFTER_RECEIPT_SECONDS,
        ]);
    }
}
