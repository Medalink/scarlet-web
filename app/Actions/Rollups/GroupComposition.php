<?php

namespace App\Actions\Rollups;

use App\Support\ArmorType;
use App\Support\GroupSpans;
use App\Support\ItemClass;
use App\Support\LootRead;
use App\Support\LootRule;
use App\Support\LootSourceKind;
use App\Support\RollType;
use App\Support\RollupRead;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class GroupComposition
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * Group composition (docs/LOOT.md, Metrics: Need share by class, Skins per corpse), from what the uploaders' groups
     * recorded of their members, with no names. `need`: the uploaders' own rolls by their class and the armour type of the
     * item, split into need, greed and pass. `skins`: the corpses read for loot and the corpses read for skinning in groups
     * of 2 or more, by whether the uploader has Skinning, the only profession a group records. `roles`: the kills an hour of
     * groups whose members were all seen, by whether the group had a tank and a healer. A class, a skinning flag and a role
     * pair that fewer than `scarlet.min_characters` characters contributed to are left out.
     *
     * @return array{need: list<array{class: string, armor: int, need: int, greed: int, pass: int}>, skins: list<array{skinner: bool, looted: int, skinned: int}>, roles: list<array{tank: bool, healer: bool, kills: int, seconds: int}>}
     */
    public function handle(): array
    {
        return ['need' => $this->need(), 'skins' => $this->skins(), 'roles' => $this->roles()];
    }

    /** @return list<array{class: string, armor: int, need: int, greed: int, pass: int}> */
    private function need(): array
    {
        $rows = $this->read->rows(DB::table('loot_rolls as r')->join('items as i', 'i.item_id', '=', 'r.item_id')
            ->where('r.counted', true)->where('r.mine', true)->whereNotNull('r.class_file')->whereIn('r.loot_rule', LootRule::rolled())
            ->where('i.class_id', ItemClass::Armor->value)->whereIn('i.subclass_id', ArmorType::ids())
            ->selectRaw('r.class_file, i.subclass_id, count(*) filter (where r.roll_type = ?) as need, count(*) filter (where r.roll_type = ?) as greed, count(*) filter (where r.roll_type = ?) as pass', [RollType::Need->value, RollType::Greed->value, RollType::Pass->value])
            ->groupBy('r.class_file', 'i.subclass_id')->havingRaw('count(distinct r.character_id) >= ?', [$this->read->threshold(null)]));

        $need = [];
        foreach ($rows as $row) {
            if ((int) $row['need'] + (int) $row['greed'] + (int) $row['pass'] > 0) {
                $need[] = ['class' => (string) $row['class_file'], 'armor' => (int) $row['subclass_id'], 'need' => (int) $row['need'], 'greed' => (int) $row['greed'], 'pass' => (int) $row['pass']];
            }
        }
        usort($need, fn (array $a, array $b): int => [$b['need'] + $b['greed'] + $b['pass'], $a['class'], $a['armor']] <=> [$a['need'] + $a['greed'] + $a['pass'], $b['class'], $b['armor']]);

        return $need;
    }

    /** @return list<array{skinner: bool, looted: int, skinned: int}> */
    private function skins(): array
    {
        $spans = DB::table('group_spans as s')->where('s.counted', true)->where('s.size', '>=', 2)->whereNotNull('s.skinners')->select('s.character_id', 's.session_start', 's.at', 's.skinners');
        $rows = $this->read->rows(GroupSpans::onto('loot_opens', 'o', $spans)
            ->where('o.counted', true)->where('o.source_kind', LootSourceKind::Creature->value)->whereNotNull('o.source_id')->whereIn('o.read', [LootRead::Loot->value, LootRead::Skin->value])
            ->where(fn (Builder $first): Builder => $first->whereNull('o.first_read')->orWhere('o.first_read', true))
            ->selectRaw('s.skinners, count(*) filter (where o.read = ?) as looted, count(*) filter (where o.read = ?) as skinned', [LootRead::Loot->value, LootRead::Skin->value])
            ->groupBy('s.skinners')->orderByDesc('s.skinners')->havingRaw('count(distinct o.character_id) >= ?', [$this->read->threshold(null)]));

        return array_map(fn (array $row): array => ['skinner' => (int) $row['skinners'] > 0, 'looted' => (int) $row['looted'], 'skinned' => (int) $row['skinned']], $rows);
    }

    /** @return list<array{tank: bool, healer: bool, kills: int, seconds: int}> */
    private function roles(): array
    {
        $threshold = $this->read->threshold(null);
        $seen = fn (): Builder => GroupSpans::timed()->whereColumn('s.members_known', '>=', 's.size');
        $hours = $this->read->rows($seen()->selectRaw('(s.tanks > 0)::int as tank, (s.healers > 0)::int as healer, sum(extract(epoch from s.ended_at - s.at)) as seconds')
            ->groupByRaw('(s.tanks > 0)::int, (s.healers > 0)::int')->havingRaw('count(distinct s.character_id) >= ?', [$threshold]));
        $kills = $this->read->rows(GroupSpans::killed($seen()->select('s.character_id', 's.session_start', 's.at', 's.tanks', 's.healers'))
            ->selectRaw('(s.tanks > 0)::int as tank, (s.healers > 0)::int as healer, count(*) as kills')
            ->groupByRaw('(s.tanks > 0)::int, (s.healers > 0)::int')->havingRaw('count(distinct k.character_id) >= ?', [$threshold]));

        $by = [];
        foreach ($kills as $row) {
            $by[(int) $row['tank'] * 2 + (int) $row['healer']]['kills'] = (int) $row['kills'];
        }
        foreach ($hours as $row) {
            $by[(int) $row['tank'] * 2 + (int) $row['healer']]['seconds'] = (int) $row['seconds'];
        }
        krsort($by);
        $rows = [];
        foreach ($by as $key => $counts) {
            if (isset($counts['kills'], $counts['seconds'])) {
                $rows[] = ['tank' => $key >= 2, 'healer' => $key % 2 === 1, 'kills' => $counts['kills'], 'seconds' => $counts['seconds']];
            }
        }

        return $rows;
    }
}
