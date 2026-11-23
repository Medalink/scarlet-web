<?php

namespace App\Support\ClassPage\Warrior;

use App\Support\ClassPage\Callout;
use App\Support\ClassPage\Icon;
use App\Support\ClassPage\Module;
use App\Support\ClassPage\Rank;
use App\Support\ClassPage\RankRow;
use App\Support\ClassPage\Tone;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\StateKind;

/** The weapon types held in the main hand and the shield on the arm, from the gear changes and the client's item classes. */
final readonly class Gear
{
    private const int WEAPON = 2;

    private const int ARMOR = 4;

    private const int SHIELD = 6;

    private const array TYPES = [
        0 => 'One-Handed Axes', 1 => 'Two-Handed Axes', 2 => 'Bows', 3 => 'Guns', 4 => 'One-Handed Maces', 5 => 'Two-Handed Maces', 6 => 'Polearms',
        7 => 'One-Handed Swords', 8 => 'Two-Handed Swords', 10 => 'Staves', 13 => 'Fist Weapons', 15 => 'Daggers', 16 => 'Thrown', 18 => 'Crossbows',
    ];

    /**
     * @param  array<string, array{ms: int, fightMs: int, item: int, best: int}>  $types  by weapon type
     */
    private function __construct(private array $types, private int $shieldMs, private int $shieldFightMs, private int $fightMs) {}

    public static function of(ClassRows $rows): self
    {
        $main = $rows->states(StateKind::MainHand)->of(StateKind::MainHand);
        $off = $rows->states(StateKind::OffHand)->of(StateKind::OffHand);
        $kinds = $rows->itemKinds(array_values(array_unique(array_map(fn ($state): int => $state->refId, [...$main, ...$off]))));
        $types = [];
        foreach ($main as $state) {
            $kind = $kinds[$state->refId] ?? null;
            $name = $kind === null || $kind['class'] !== self::WEAPON ? null : (self::TYPES[$kind['subclass']] ?? null);
            if ($name === null) {
                continue;
            }
            $types[$name] ??= ['ms' => 0, 'fightMs' => 0, 'item' => 0, 'best' => -1];
            $types[$name]['ms'] += $state->ms;
            $types[$name]['fightMs'] += $state->fightMs;
            if ($state->ms > $types[$name]['best']) {
                $types[$name]['best'] = $state->ms;
                $types[$name]['item'] = $state->refId;
            }
        }
        uasort($types, fn (array $a, array $b): int => $b['ms'] <=> $a['ms']);
        $shield = $shieldFight = 0;
        foreach ($off as $state) {
            $kind = $kinds[$state->refId] ?? null;
            if ($kind !== null && $kind['class'] === self::ARMOR && $kind['subclass'] === self::SHIELD) {
                $shield += $state->ms;
                $shieldFight += $state->fightMs;
            }
        }

        return new self($types, $shield, $shieldFight, $rows->states(StateKind::Fight)->fights()->ms);
    }

    /** @return list<Callout> */
    public function callouts(Attacks $attacks): array
    {
        $callouts = [];
        $top = $attacks->top();
        if ($top !== null) {
            $callouts[] = new Callout('dummy', $top->name, 'The attack used most', Unit::Count->say($top->casts / $attacks->total() * 100).'% of attacks');
        }
        $type = array_key_first($this->types);
        if ($type !== null) {
            $callouts[] = new Callout('weaponrack', $type, 'In the main hand for '.Unit::Hours->say($this->types[$type]['ms'] / 3_600_000).' hours', $this->share($this->types[$type]['fightMs']).' of fight time');
        }
        if ($this->shieldMs > 0) {
            $callouts[] = new Callout('armorstand', 'Shield', 'On the arm for '.Unit::Hours->say($this->shieldMs / 3_600_000).' hours', $this->share($this->shieldFightMs).' of fight time');
        }

        return $callouts;
    }

    public function module(): ?Module
    {
        if ($this->types === []) {
            return null;
        }
        $rows = [];
        foreach ($this->types as $name => $type) {
            $rows[] = new RankRow($name, $type['ms'] / 3_600_000, $type['item'] === 0 ? null : Icon::item($type['item']), Tone::Own);
        }

        return new Module('Weapons in the main hand', [new Rank($rows, Unit::Hours, true)], note: 'Counted from the first time the equipment changed in each session; the time before it is not known.');
    }

    private function share(int $ms): string
    {
        return Unit::Count->say($this->fightMs === 0 ? 0 : min(100, $ms / $this->fightMs * 100)).'%';
    }
}
