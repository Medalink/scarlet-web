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
 * that answer in aggregate SQL, counted rows only. The spell, tally and meter readers are built on first use and kept.
 * The item and state readers take what a builder asks for (item names, state kinds) and read only those; a later
 * request that an earlier read already covers is answered from it, and a request with no argument reads everything.
 * Every summed column is held to `CAP`, so adding the rows of a crafted upload cannot pass the integer range. Four
 * small reads beside them answer what the tables cannot: the description of a spell, the kills a party member got,
 * the time played and the level axis of a chart, which starts at the first level with the rows the chart reads.
 */
final class ClassRows
{
    /** The largest a summed column reads as: 2^52, so a thousand rows still add up inside an integer. */
    public const int CAP = 4_503_599_627_370_496;

    /** The highest level a chart draws a column for. */
    public const int TOP_LEVEL = 60;

    private ?SpellBook $spells = null;

    /** @var list<array{kinds: list<string>|null, times: StateTimes}> */
    private array $states = [];

    /** @var list<array{patterns: list<string>, flows: ItemFlows}> */
    private array $items = [];

    private ?Tallies $tallies = null;

    private ?Meters $meters = null;

    /** @var array<int, int>|null */
    private ?array $played = null;

    /** @var array<string, bool>|null */
    private ?array $holds = null;

    public function __construct(public readonly int $characterId, public readonly int $level, private readonly ResolveNames $names) {}

    public static function of(Character $character, ResolveNames $names): self
    {
        return new self((int) $character->id, (int) $character->level, $names);
    }

    public function spells(): SpellBook
    {
        if ($this->spells === null) {
            $rows = self::fetch($this->counted('spell_casts')->groupBy('spell_id', 'level')
                ->selectRaw(
                    'spell_id, level, least(coalesce(sum(casts), 0), ?) as casts, least(coalesce(sum(fight_casts), 0), ?) as fight, least(coalesce(sum(cast_ms), 0), ?) as cast_ms, least(coalesce(sum(channel_ms), 0), ?) as channel_ms, least(coalesce(sum(interrupts), 0), ?) as interrupts, max(streak) as streak',
                    array_fill(0, 5, self::CAP),
                ));
            $this->spells = new SpellBook($rows, $this->names->handle(NameKind::Spell, self::ids($rows, 'spell_id')));
        }

        return $this->spells;
    }

    /** The state times of these kinds, or of every kind with none named. */
    public function states(StateKind ...$kinds): StateTimes
    {
        $wanted = array_values(array_unique(array_map(fn (StateKind $kind): string => $kind->value, $kinds)));
        sort($wanted);
        foreach ($this->states as $read) {
            if ($wanted === [] ? $read['kinds'] === null : ($read['kinds'] === null || array_diff($wanted, $read['kinds']) === [])) {
                return $read['times'];
            }
        }
        $times = $this->readStates($wanted);
        $this->states[] = ['kinds' => $wanted === [] ? null : $wanted, 'times' => $times];

        return $times;
    }

    /** The item flows of the items whose name matches a pattern (`*` is any text), or of every item with none named. */
    public function items(string ...$patterns): ItemFlows
    {
        $wanted = array_values(array_unique($patterns));
        sort($wanted);
        foreach ($this->items as $read) {
            if ($read['patterns'] === [] || ($wanted !== [] && array_diff($wanted, $read['patterns']) === [])) {
                return $read['flows'];
            }
        }
        $flows = $this->readItems($wanted);
        $this->items[] = ['patterns' => $wanted, 'flows' => $flows];

        return $flows;
    }

    public function tallies(): Tallies
    {
        if ($this->tallies === null) {
            $this->tallies = new Tallies(self::fetch($this->counted('class_tallies')->groupBy('key', 'a', 'b')
                ->selectRaw('key, a, b, least(coalesce(sum(n), 0), ?) as n, least(coalesce(sum(sum), 0), ?) as sum, max(best) as best', [self::CAP, self::CAP])));
        }

        return $this->tallies;
    }

    public function meters(): Meters
    {
        if ($this->meters === null) {
            $totals = self::fetch($this->counted('meter_totals')->groupBy('meter', 'level', 'place')
                ->selectRaw('meter, level, place, least(coalesce(sum(total), 0), ?) as total, max(best) as best, least(coalesce(sum(fights), 0), ?) as fights, least(coalesce(sum(fight_ms), 0), ?) as fight_ms', array_fill(0, 3, self::CAP)));
            $spells = self::fetch($this->counted('meter_spells')->groupBy('meter', 'spell_id')
                ->selectRaw('meter, spell_id, least(coalesce(sum(amount), 0), ?) as amount, least(coalesce(sum(fights), 0), ?) as fights', array_fill(0, 2, self::CAP)));
            $this->meters = new Meters($totals, $spells, $this->names->handle(NameKind::Spell, self::ids($spells, 'spell_id')));
        }

        return $this->meters;
    }

    /** Whether the character has a counted row in a class table at all: false when the sharing group that fills it is not shared. */
    public function holds(ClassTable $table): bool
    {
        if ($this->holds === null) {
            $query = DB::query();
            foreach (ClassTable::cases() as $case) {
                $query->selectSub($this->counted($case->value)->selectRaw('1')->limit(1), $case->value);
            }
            $this->holds = array_map(fn (mixed $found): bool => $found !== null, (array) $query->first());
        }

        return $this->holds[$table->value] ?? false;
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
            $this->played = array_map(fn (mixed $ms): int => (int) $ms, $this->counted('play_spans')->groupBy('level')->selectRaw('coalesce(level, 0) as level, least(coalesce(sum(ms), 0), ?) as ms', [self::CAP])->pluck('ms', 'level')->all());
            ksort($this->played);
        }

        return $this->played;
    }

    /** @return list<int> the levels a by-level chart draws: from the first level with the rows the chart reads ($from) to the character's level, up to `TOP_LEVEL` */
    public function levels(int $from): array
    {
        $last = min($this->level, self::TOP_LEVEL);
        if ($last < 1) {
            return [];
        }

        return range(max(1, min($from, $last)), $last);
    }

    /** @param  list<string>  $kinds */
    private function readStates(array $kinds): StateTimes
    {
        $query = $this->counted('state_times')->groupBy('kind', 'ref_id', 'name', 'extra', 'level')
            ->selectRaw('kind, ref_id, name, extra, level, least(coalesce(sum(starts), 0), ?) as starts, least(coalesce(sum(ms), 0), ?) as ms, least(coalesce(sum(fight_ms), 0), ?) as fight_ms', array_fill(0, 3, self::CAP));
        if ($kinds !== []) {
            $query->whereIn('kind', $kinds);
        }
        $rows = self::fetch($query);
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

        return new StateTimes($rows, $this->names->handle(NameKind::Spell, $spellRefs), $this->names->handle(NameKind::Item, $itemRefs));
    }

    /** @param  list<string>  $patterns */
    private function readItems(array $patterns): ItemFlows
    {
        $query = $this->counted('item_flows')->groupBy('item_id', 'dir', 'place', 'spell_id')
            ->selectRaw('item_id, dir, place, spell_id, least(coalesce(sum(quantity), 0), ?) as quantity', [self::CAP]);
        if ($patterns !== []) {
            $query->whereIn('item_id', function (Builder $names) use ($patterns): void {
                $names->select('id')->from('names')->where('kind', NameKind::Item->value)->where(function (Builder $named) use ($patterns): void {
                    foreach ($patterns as $pattern) {
                        $named->orWhere('name', 'ilike', strtr($pattern, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_', '*' => '%']));
                    }
                });
            });
        }
        $rows = self::fetch($query);

        return new ItemFlows($rows, $this->names->handle(NameKind::Item, self::ids($rows, 'item_id')));
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
