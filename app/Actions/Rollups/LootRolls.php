<?php

namespace App\Actions\Rollups;

use App\Support\LootRead;
use App\Support\LootRule;
use App\Support\LootSourceKind;
use App\Support\RollType;
use App\Support\RollupRead;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class LootRolls
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * Rolls (docs/LOOT.md, Metrics: Need share, Rolled per 100 corpses). `choices`: the uploaders' own rolls by loot rule
     * and item quality, each counted once whoever else rolled, split into need, greed and pass; a roll another member
     * made reaches every uploader of the group, so it is counted by none of them. `thresholds`: by rule and threshold, the
     * corpses read for loot in a group of 2 or more and the items on them that were at or above the threshold, which is
     * what the game rolls for. A rule and quality, or a rule and threshold, under `scarlet.min_characters` characters are
     * left out. A roll of another type (disenchant, transmog) is in no figure.
     *
     * @return array{choices: list<array{rule: string, quality: int|null, need: int, greed: int, pass: int}>, thresholds: list<array{rule: string, threshold: int, corpses: int, items: int}>}
     */
    public function handle(): array
    {
        $threshold = $this->read->threshold(null);
        $choices = $this->read->rows(DB::table('loot_rolls as r')->leftJoin('items as i', 'i.item_id', '=', 'r.item_id')
            ->where('r.counted', true)->where('r.mine', true)->whereIn('r.loot_rule', LootRule::rolled())
            ->selectRaw('r.loot_rule, i.quality, count(*) filter (where r.roll_type = ?) as need, count(*) filter (where r.roll_type = ?) as greed, count(*) filter (where r.roll_type = ?) as pass', [RollType::Need->value, RollType::Greed->value, RollType::Pass->value])
            ->groupBy('r.loot_rule', 'i.quality')->orderBy('r.loot_rule')->orderBy('i.quality')
            ->havingRaw('count(distinct r.character_id) >= ?', [$threshold]));
        $corpses = $this->read->rows($this->windows(DB::table('loot_opens')->where('has_items', true), '')
            ->selectRaw('loot_rule, threshold, count(*) as corpses')->groupBy('loot_rule', 'threshold')
            ->havingRaw('count(distinct character_id) >= ?', [$threshold]));
        $items = $this->read->rows($this->windows(DB::table('loot_drops as d')->join('items as i', 'i.item_id', '=', 'd.item_id')->whereColumn('i.quality', '>=', 'd.threshold')->where('d.via', 'window'), 'd.')
            ->selectRaw('d.loot_rule, d.threshold, count(*) as items')->groupBy('d.loot_rule', 'd.threshold'));
        $onItems = [];
        foreach ($items as $row) {
            $onItems[$row['loot_rule'].'|'.$row['threshold']] = (int) $row['items'];
        }

        return [
            'choices' => array_map(fn (array $row): array => [
                'rule' => (string) $row['loot_rule'], 'quality' => $row['quality'] === null ? null : (int) $row['quality'],
                'need' => (int) $row['need'], 'greed' => (int) $row['greed'], 'pass' => (int) $row['pass'],
            ], array_values(array_filter($choices, fn (array $row): bool => (int) $row['need'] + (int) $row['greed'] + (int) $row['pass'] > 0))),
            'thresholds' => array_map(fn (array $row): array => [
                'rule' => (string) $row['loot_rule'], 'threshold' => (int) $row['threshold'], 'corpses' => (int) $row['corpses'], 'items' => $onItems[$row['loot_rule'].'|'.$row['threshold']] ?? 0,
            ], array_values(array_filter($corpses, fn (array $row): bool => $row['threshold'] !== null))),
        ];
    }

    /** The first reads of creature corpses read for loot, in a group of 2 or more, under a rule that rolls. */
    private function windows(Builder $query, string $prefix = ''): Builder
    {
        return LootRead::Loot->within($query->where($prefix.'counted', true)->where($prefix.'source_kind', LootSourceKind::Creature->value)
            ->whereNotNull($prefix.'source_id')->whereNotNull($prefix.'threshold')->where($prefix.'group_size', '>=', 2)->whereIn($prefix.'loot_rule', LootRule::rolled())
            ->where(fn (Builder $first): Builder => $first->whereNull($prefix.'first_read')->orWhere($prefix.'first_read', true)), $prefix.'read');
    }
}
