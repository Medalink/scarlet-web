<?php

namespace App\Support\ClassPage\Hunter;

use App\Support\ClassPage\Bag;
use App\Support\ClassPage\BagItem;
use App\Support\ClassPage\Flow;
use App\Support\ClassPage\FlowItem;
use App\Support\ClassPage\Icon;
use App\Support\ClassPage\Module;
use App\Support\ClassPage\Pair;
use App\Support\ClassPage\PairRow;
use App\Support\ClassPage\Span;
use App\Support\ClassPage\Tone;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\ClassTable;
use App\Support\ClassRows\FlowDirection;
use App\Support\ClassRows\FlowPlace;
use App\Support\ClassRows\ItemFlow;
use App\Support\ClassRows\ItemFlows;
use App\Support\StateKind;

/** The ammo that moved through the bags (`item_flows` of the items the client calls projectiles): where it came from, what fired it and what is left. */
final readonly class Ammo
{
    private const int PROJECTILE = 6;

    private const int STACK = 200;

    private const int SHOWN = 4;

    /** @var array<int, ItemFlow> the flow of each ammo item, by item id */
    private array $items;

    /** @var list<int> the spell ids the client names Auto Shot */
    private array $autoShot;

    private ItemFlows $flows;

    public function __construct(private ClassRows $rows)
    {
        $this->flows = $rows->itemsOfClass(self::PROJECTILE);
        $items = [];
        foreach ($this->flows->ids() as $id) {
            $items[$id] = $this->flows->ofItems($id);
        }
        $this->items = $items;
        $spells = [];
        foreach ($items as $flow) {
            $spells = [...$spells, ...array_keys($flow->spellUnits(FlowDirection::Out))];
        }
        $names = $rows->spellNames(array_values(array_unique($spells)));
        $this->autoShot = array_keys(array_filter($names, fn (string $name): bool => $name === 'Auto Shot'));
    }

    /** Whether the character shares item rows at all; without them every ammo figure is withheld, not 0. */
    public function shared(): bool
    {
        return $this->rows->holds(ClassTable::Items);
    }

    /** The shots fired: ammo that left the bags while Auto Shot ran. */
    public function fired(): int
    {
        return array_sum(array_map(fn (ItemFlow $flow): int => $flow->bySpells(FlowDirection::Out, $this->autoShot), $this->items));
    }

    public function bought(): int
    {
        return array_sum(array_map(fn (ItemFlow $flow): int => $flow->sum(FlowDirection::In, FlowPlace::Vendor), $this->items));
    }

    public function held(): int
    {
        return array_sum(array_map(fn (ItemFlow $flow): int => $flow->held(), $this->items));
    }

    public function chart(): ?Module
    {
        if ($this->items === []) {
            return null;
        }
        $in = fn (FlowPlace ...$places): int => array_sum(array_map(fn (ItemFlow $flow): int => array_sum(array_map(fn (FlowPlace $place): int => $flow->sum(FlowDirection::In, $place), $places)), $this->items));
        $came = array_sum(array_map(fn (ItemFlow $flow): int => $flow->in(), $this->items));
        $left = [
            new FlowItem('Bought from vendors', $in(FlowPlace::Vendor)),
            new FlowItem('Crafted', $in(FlowPlace::Profession)),
            new FlowItem('Looted', $in(FlowPlace::Loot)),
            new FlowItem('Mailed or traded in', $in(FlowPlace::Mail, FlowPlace::Trade)),
        ];
        $known = array_sum(array_map(fn (FlowItem $item): int|float => $item->count, $left));
        $left[] = new FlowItem('Another way', max(0, $came - $known));
        $left = array_values(array_filter($left, fn (FlowItem $item): bool => $item->count > 0));
        $fired = [];
        foreach ($this->items as $id => $flow) {
            $count = $flow->bySpells(FlowDirection::Out, $this->autoShot);
            if ($count > 0) {
                $fired[$id] = $count;
            }
        }
        arsort($fired);
        $right = [];
        foreach (array_slice($fired, 0, self::SHOWN, true) as $id => $count) {
            $right[] = new FlowItem($this->flows->nameOf($id) ?? 'Ammo', $count, Icon::item($id));
        }
        $rest = array_sum($fired) - array_sum(array_map(fn (FlowItem $item): int|float => $item->count, $right));
        if ($rest > 0) {
            $right[] = new FlowItem('Other ammo fired', $rest);
        }
        $out = array_sum(array_map(fn (ItemFlow $flow): int => $flow->out(), $this->items));
        $other = max(0, $out - $this->fired());
        if ($other > 0) {
            $right[] = new FlowItem('Sold, traded or destroyed', $other, null, Tone::Lost);
        }
        if ($this->held() > 0) {
            $right[] = new FlowItem('In the quiver now', $this->held(), null, Tone::Held);
        }
        if ($left === [] || $right === []) {
            return null;
        }

        return new Module('Where the ammo came from and where it went', [new Flow($left, $right, 'shots', 'The ammo that came in, on the left, and what it was fired, sold or kept as, on the right, in shots')], Span::W8);
    }

    public function quiver(): ?Module
    {
        if ($this->items === []) {
            return null;
        }
        $stacks = [];
        $total = 0;
        foreach ($this->items as $id => $flow) {
            $slots = (int) ceil($flow->held() / self::STACK);
            if ($slots > 0) {
                $stacks[] = new BagItem(Icon::item($id), $slots);
                $total += $slots;
            }
        }
        $fights = $this->rows->states(StateKind::Fight)->fights()->starts;
        $blocks = [];
        if ($stacks !== []) {
            $blocks[] = new Bag($total, $stacks, 'stack of ammo');
        }
        $blocks[] = new Pair([
            new PairRow('Shots fired', $this->fired()),
            new PairRow('Shots a fight', $fights === 0 ? 0 : $this->fired() / $fights, Unit::Decimal),
            new PairRow('Ammo bought', $this->bought()),
        ]);

        return new Module('The quiver', $blocks, note: 'Stacks of 200 in the bags now, counted from the changes the addon saw.');
    }
}
