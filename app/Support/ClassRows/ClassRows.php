<?php

namespace App\Support\ClassRows;

use App\Actions\Public\ResolveNames;
use App\Models\Character;
use App\Support\NameKind;
use App\Support\StateKind;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * What a class page reads of one character: the six class tables (docs/ROWS.md, "The class tables") as typed readers
 * that answer in aggregate SQL, counted rows only, one query a table. Each reader is built on first use and kept, so
 * a builder may ask for it as often as it likes. Four small reads beside them answer what the tables cannot: the
 * description of a spell, the kills a party member got, the time played and the level axis of a chart.
 */
final class ClassRows
{
    private ?SpellBook $spells = null;

    private ?StateTimes $states = null;

    private ?ItemFlows $items = null;

    private ?Tallies $tallies = null;

    private ?Meters $meters = null;

    /** @var array<int, int>|null */
    private ?array $played = null;

    public function __construct(public readonly int $characterId, public readonly int $level, private readonly ResolveNames $names) {}

    public static function of(Character $character, ResolveNames $names): self
    {
        return new self((int) $character->id, (int) $character->level, $names);
    }

    public function spells(): SpellBook
    {
        if ($this->spells === null) {
            $rows = self::fetch($this->counted('spell_casts')->groupBy('spell_id', 'level')
                ->selectRaw('spell_id, level, sum(casts) as casts, sum(fight_casts) as fight, sum(cast_ms) as cast_ms, sum(channel_ms) as channel_ms, sum(interrupts) as interrupts, max(streak) as streak'));
            $this->spells = new SpellBook($rows, $this->names->handle(NameKind::Spell, self::ids($rows, 'spell_id')));
        }

        return $this->spells;
    }

    public function states(): StateTimes
    {
        if ($this->states === null) {
            $rows = self::fetch($this->counted('state_times')->groupBy('kind', 'ref_id', 'name', 'extra', 'level')
                ->selectRaw('kind, ref_id, name, extra, level, sum(starts) as starts, sum(ms) as ms, coalesce(sum(fight_ms), 0) as fight_ms'));
            $spellRefs = $itemRefs = [];
            foreach ($rows as $row) {
                if ($row->name !== null && $row->name !== '') {
                    continue;
                }
                if (in_array($row->kind, [StateKind::MainHand->value, StateKind::OffHand->value], true)) {
                    $itemRefs[] = (int) $row->ref_id;

                    continue;
                }
                $spellRefs[] = (int) $row->ref_id;
            }
            $this->states = new StateTimes($rows, $this->names->handle(NameKind::Spell, $spellRefs), $this->names->handle(NameKind::Item, $itemRefs));
        }

        return $this->states;
    }

    public function items(): ItemFlows
    {
        if ($this->items === null) {
            $rows = self::fetch($this->counted('item_flows')->groupBy('item_id', 'dir', 'place', 'spell_id')
                ->selectRaw('item_id, dir, place, spell_id, sum(quantity) as quantity'));
            $this->items = new ItemFlows($rows, $this->names->handle(NameKind::Item, self::ids($rows, 'item_id')));
        }

        return $this->items;
    }

    public function tallies(): Tallies
    {
        if ($this->tallies === null) {
            $this->tallies = new Tallies(self::fetch($this->counted('class_tallies')->groupBy('key', 'a', 'b')
                ->selectRaw('key, a, b, sum(n) as n, sum(sum) as sum, max(best) as best')));
        }

        return $this->tallies;
    }

    public function meters(): Meters
    {
        if ($this->meters === null) {
            $totals = self::fetch($this->counted('meter_totals')->groupBy('meter', 'level', 'place')
                ->selectRaw('meter, level, place, sum(total) as total, max(best) as best, sum(fights) as fights, sum(fight_ms) as fight_ms'));
            $spells = self::fetch($this->counted('meter_spells')->groupBy('meter', 'spell_id')
                ->selectRaw('meter, spell_id, sum(amount) as amount, sum(fights) as fights'));
            $this->meters = new Meters($totals, $spells, $this->names->handle(NameKind::Spell, self::ids($spells, 'spell_id')));
        }

        return $this->meters;
    }

    /**
     * The client's names of spells that a row names without a cast: the id a self-resurrection offered, an aura gained.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public function spellNames(array $ids): array
    {
        return $this->names->handle(NameKind::Spell, $ids);
    }

    /**
     * The description of each spell the client worded, for the numbers a spell's own text gives.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public function descriptions(array $ids): array
    {
        $text = [];
        foreach (DB::table('spells')->whereIn('spell_id', $ids)->whereNotNull('description')->get(['spell_id', 'description']) as $row) {
            $text[(int) $row->spell_id] = (string) $row->description;
        }

        return $text;
    }

    /** Creatures that died to a party member's blow: the `kills` the character did not get the credit for. */
    public function partyKills(): int
    {
        return (int) $this->counted('kills')->where('player', false)->where('credit', 0)->count();
    }

    /** The character's played time over the sessions that count, in milliseconds. */
    public function playedMs(): int
    {
        return array_sum($this->playedByLevel());
    }

    /** @return array<int, int> played milliseconds by level, 0 for a span with no level */
    public function playedByLevel(): array
    {
        if ($this->played === null) {
            $this->played = array_map(fn (mixed $ms): int => (int) $ms, $this->counted('play_spans')->groupBy('level')->selectRaw('coalesce(level, 0) as level, sum(ms) as ms')->pluck('ms', 'level')->all());
            ksort($this->played);
        }

        return $this->played;
    }

    /** @return list<int> the levels a by-level chart draws: from the first level with rows to the character's level */
    public function levels(): array
    {
        $first = array_filter([$this->spells()->firstLevel(), $this->states()->firstLevel(), $this->meters()->firstLevel()], fn (?int $level): bool => $level !== null);
        if ($first === [] || $this->level < 1) {
            return [];
        }

        return range(min(min($first), $this->level), $this->level);
    }

    /** @return list<\stdClass> */
    private static function fetch(Builder $query): array
    {
        return array_values($query->get()->all());
    }

    /**
     * @param  list<\stdClass>  $rows
     * @return list<int>
     */
    private static function ids(array $rows, string $column): array
    {
        return array_map(fn (object $row): int => (int) $row->{$column}, $rows);
    }

    private function counted(string $table): Builder
    {
        return DB::table($table)->where('character_id', $this->characterId)->where('counted', true);
    }
}
