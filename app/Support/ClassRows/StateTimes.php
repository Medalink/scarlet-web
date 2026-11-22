<?php

namespace App\Support\ClassRows;

use App\Support\StateKind;

/** The `state_times` rows of a character, summed by state, reference and level (docs/ROWS.md, `state_times`). */
final class StateTimes
{
    /** @var array<string, list<StateTotals>> by kind */
    private array $byKind = [];

    /**
     * @param  list<\stdClass>  $rows  columns kind, ref_id, name, extra, level, starts, ms, fight_ms
     * @param  array<int, string>  $spellNames  names of the spells the aura, totem and tracking kinds reference
     * @param  array<int, string>  $itemNames  names of the items the weapon kinds reference
     */
    public function __construct(array $rows, array $spellNames, array $itemNames)
    {
        $grouped = [];
        foreach ($rows as $row) {
            $referenced = in_array($row->kind, [StateKind::MainHand->value, StateKind::OffHand->value], true) ? $itemNames : $spellNames;
            $name = (string) ($row->name ?? '') !== '' ? (string) $row->name : ($referenced[(int) $row->ref_id] ?? '');
            $key = $row->kind.'|'.$row->ref_id.'|'.$name.'|'.$row->extra;
            $level = $row->level === null ? null : (int) $row->level;
            $grouped[$key] ??= ['kind' => (string) $row->kind, 'ref' => (int) $row->ref_id, 'name' => $name, 'extra' => (string) ($row->extra ?? ''), 'starts' => 0, 'ms' => 0, 'fight' => 0, 'levels' => []];
            $grouped[$key]['starts'] += (int) $row->starts;
            $grouped[$key]['ms'] += (int) $row->ms;
            $grouped[$key]['fight'] += (int) $row->fight_ms;
            if ($level !== null) {
                $at = $grouped[$key]['levels'][$level] ?? ['starts' => 0, 'ms' => 0, 'fightMs' => 0];
                $grouped[$key]['levels'][$level] = ['starts' => $at['starts'] + (int) $row->starts, 'ms' => $at['ms'] + (int) $row->ms, 'fightMs' => $at['fightMs'] + (int) $row->fight_ms];
            }
        }
        foreach ($grouped as $one) {
            ksort($one['levels']);
            $this->byKind[$one['kind']][] = new StateTotals($one['ref'], $one['name'], $one['extra'], $one['starts'], $one['ms'], $one['fight'], $one['levels']);
        }
        foreach ($this->byKind as $kind => $list) {
            usort($list, fn (StateTotals $a, StateTotals $b): int => [$b->ms, $a->name] <=> [$a->ms, $b->name]);
            $this->byKind[$kind] = $list;
        }
    }

    /**
     * The states of a kind, the longest first. With patterns, only those whose name matches one (`*` is any text).
     *
     * @return list<StateTotals>
     */
    public function of(StateKind $kind, string ...$patterns): array
    {
        $all = $this->byKind[$kind->value] ?? [];

        return $patterns === [] ? $all : array_values(array_filter($all, fn (StateTotals $state): bool => SpellBook::matches($patterns, $state->name)));
    }

    /** The time and starts of every state of a kind whose name matches, as one. */
    public function sum(StateKind $kind, string ...$patterns): StateTotals
    {
        $ms = $starts = $fight = 0;
        $levels = [];
        foreach ($this->of($kind, ...$patterns) as $state) {
            $ms += $state->ms;
            $starts += $state->starts;
            $fight += $state->fightMs;
            foreach ($state->byLevel as $level => $row) {
                $at = $levels[$level] ?? ['starts' => 0, 'ms' => 0, 'fightMs' => 0];
                $levels[$level] = ['starts' => $at['starts'] + $row['starts'], 'ms' => $at['ms'] + $row['ms'], 'fightMs' => $at['fightMs'] + $row['fightMs']];
            }
        }
        ksort($levels);

        return new StateTotals(0, '', '', $starts, $ms, $fight, $levels);
    }

    /** Fights and the time they took, in all and by level. */
    public function fights(): StateTotals
    {
        return $this->sum(StateKind::Fight);
    }

    public function firstLevel(): ?int
    {
        $levels = [];
        foreach ($this->byKind as $list) {
            foreach ($list as $state) {
                array_push($levels, ...array_keys($state->byLevel));
            }
        }

        return $levels === [] ? null : min($levels);
    }
}
