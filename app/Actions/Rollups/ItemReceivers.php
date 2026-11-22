<?php

namespace App\Actions\Rollups;

use App\Support\GroupLootKind;
use App\Support\LootRule;
use App\Support\LootVia;
use App\Support\RollOutcome;
use App\Support\RollType;
use App\Support\RollupRead;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ItemReceivers
{
    /**
     * The class of a roller: the class the roll's line named (a `social` part), else the uploader's own class on its
     * character row, but only for a session that has a `group_spans` row, since a removed `social` part deletes them and
     * the class must not come back from the character (docs/LOOT.md, Decisions, 4).
     */
    private const string ROLLER_CLASS = 'upper(coalesce(r.class_file, case when r.mine and exists (select 1 from group_spans as s where s.character_id = r.character_id and s.session_start = r.session_start and s.counted) then c.class end))';

    public function __construct(private readonly RollupRead $read) {}

    /**
     * Who received one item, by class (docs/LOOT.md, Item page, Who received it): what the uploaders received from a loot
     * window in a group of 2 or more, by their own class (only from a session that has a social part), and what their
     * group mates received (`group_loot`, kind looted,
     * with the class the member's line named), and
     * for each class the rolls of need or greed that it made on the item and how many it won. Every figure is one
     * uploader's view of its own group, so a group with several uploaders is counted once for each. A class whose receipts
     * or rolls fewer than `scarlet.min_characters` characters contributed to has none of them, and a class that received
     * nothing is left out. The class with the most receipts comes first.
     *
     * @return list<array{class: string, received: int, rolled: int, won: int}>
     */
    public function handle(int $itemId): array
    {
        $threshold = $this->read->threshold(null);
        $others = $this->read->rows(DB::table('group_loot')->where('counted', true)->where('item_id', $itemId)->where('kind', GroupLootKind::Looted->value)->whereNotNull('class_file')
            ->selectRaw('upper(class_file) as class, count(*) as received')->groupByRaw('upper(class_file)')
            ->havingRaw('count(distinct character_id) >= ?', [$threshold]));
        $own = $this->read->rows(DB::table('loot_drops as d')->join('characters as c', 'c.id', '=', 'd.character_id')
            ->where('d.counted', true)->where('d.item_id', $itemId)->where('d.via', LootVia::Receipt->value)->whereNotNull('d.source_kind')->where('d.group_size', '>=', 2)->whereNotNull('c.class')
            ->whereExists(fn (Builder $social): Builder => $social->selectRaw('1')->from('group_spans as s')
                ->whereColumn('s.character_id', 'd.character_id')->whereColumn('s.session_start', 'd.session_start')->where('s.counted', true))
            ->selectRaw('upper(c.class) as class, count(*) as received')->groupByRaw('upper(c.class)')
            ->havingRaw('count(distinct d.character_id) >= ?', [$threshold]));
        $rolls = $this->read->rows(DB::table('loot_rolls as r')->leftJoin('characters as c', 'c.id', '=', 'r.character_id')
            ->where('r.counted', true)->where('r.item_id', $itemId)->whereIn('r.loot_rule', LootRule::rolled())->whereIn('r.roll_type', [RollType::Need->value, RollType::Greed->value])
            ->selectRaw(self::ROLLER_CLASS.' as class, count(*) as rolled, count(*) filter (where r.outcome = ?) as won', [RollOutcome::Won->value])
            ->groupByRaw(self::ROLLER_CLASS)
            ->havingRaw('count(distinct r.character_id) >= ?', [$threshold]));

        $by = [];
        foreach ([...$others, ...$own] as $row) {
            $by[(string) $row['class']]['received'] = ($by[(string) $row['class']]['received'] ?? 0) + (int) $row['received'];
        }
        foreach ($rolls as $row) {
            if ($row['class'] !== null) {
                $by[(string) $row['class']]['rolled'] = (int) $row['rolled'];
                $by[(string) $row['class']]['won'] = (int) $row['won'];
            }
        }
        $rows = [];
        foreach ($by as $class => $counts) {
            if (($counts['received'] ?? 0) > 0) {
                $rows[] = ['class' => (string) $class, 'received' => $counts['received'], 'rolled' => $counts['rolled'] ?? 0, 'won' => $counts['won'] ?? 0];
            }
        }
        usort($rows, fn (array $a, array $b): int => [$b['received'], $a['class']] <=> [$a['received'], $b['class']]);

        return $rows;
    }
}
