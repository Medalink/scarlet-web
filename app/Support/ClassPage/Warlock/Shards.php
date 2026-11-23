<?php

namespace App\Support\ClassPage\Warlock;

use App\Support\ClassPage\Bag;
use App\Support\ClassPage\BagItem;
use App\Support\ClassPage\Flow;
use App\Support\ClassPage\FlowItem;
use App\Support\ClassPage\Icon;
use App\Support\ClassPage\Module;
use App\Support\ClassPage\Pair;
use App\Support\ClassPage\PairRow;
use App\Support\ClassPage\Row;
use App\Support\ClassPage\Span;
use App\Support\ClassPage\Tone;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\ClassTable;
use App\Support\ClassRows\FlowDirection;
use App\Support\ClassRows\ItemFlow;
use App\Support\ClassRows\SpellTotals;

/** Where the character's soul shards came from and what they bought, from the Soul Shard's `item_flows` rows tied to the spell of each change. */
final readonly class Shards
{
    /** @param  bool  $shared  whether the character has any item flow rows at all; without them every shard figure is withheld, not 0 */
    public function __construct(public ItemFlow $flow, public ?Icon $icon, private Spells $spells, public bool $shared = true) {}

    public static function of(ClassRows $rows, Spells $spells): self
    {
        $items = $rows->items('Soul Shard');
        $ids = $items->idsNamed('Soul Shard');

        return new self($items->named('Soul Shard'), $ids === [] ? null : Icon::item($ids[0]), $spells, $rows->holds(ClassTable::Items));
    }

    /** Every shard that came into the bags. */
    public function made(): int
    {
        return $this->flow->in();
    }

    public function destroyed(): int
    {
        return $this->flow->destroyed();
    }

    public function row(): ?Row
    {
        if (! $this->shared || $this->made() === 0) {
            return null;
        }

        return new Row([
            new Module('Where soul shards came from and what they bought', [$this->chart()], Span::W8, $this->note()),
            new Module('Shards in the bags', [
                new Bag(Bag::MOST_SLOTS, [new BagItem($this->icon, min($this->flow->held(), Bag::MOST_SLOTS))], 'soul shard'),
                new Pair([new PairRow('In the bags now', $this->flow->held())]),
            ]),
        ]);
    }

    private function chart(): Flow
    {
        $in = fn (SpellTotals $spell): int => $this->flow->bySpells(FlowDirection::In, $spell->ids());
        $out = fn (SpellTotals $spell): int => $this->flow->bySpells(FlowDirection::Out, $spell->ids());
        $left = [
            new FlowItem('Drain Soul', $in($this->spells->drainSoul), Spells::icon($this->spells->drainSoul)),
            new FlowItem('Shadowburn', $in($this->spells->shadowburn), Spells::icon($this->spells->shadowburn)),
        ];
        $left[] = new FlowItem('Other', $this->made() - $left[0]->count - $left[1]->count);
        $right = [
            new FlowItem('Summoning a demon', $out($this->spells->summons), Spells::icon($this->spells->summons)),
            new FlowItem('Shadowburn', $out($this->spells->shadowburn), Spells::icon($this->spells->shadowburn)),
            new FlowItem('Soul Fire', $out($this->spells->soulFire), Spells::icon($this->spells->soulFire)),
            new FlowItem('Healthstones', $out($this->spells->healthstones), Spells::icon($this->spells->healthstones)),
            new FlowItem('Soulstones', $out($this->spells->soulstones), Spells::icon($this->spells->soulstones)),
            new FlowItem('Ritual of Summoning', $out($this->spells->ritual), Spells::icon($this->spells->ritual)),
        ];
        $listed = array_sum(array_map(fn (FlowItem $item): int => (int) $item->count, $right));
        $right[] = new FlowItem('Destroyed to make room', $this->destroyed(), $this->icon, Tone::Lost);
        $right[] = new FlowItem('Other spells', max(0, $this->flow->out() - $this->destroyed() - $listed));
        $right[] = new FlowItem('In the bags now', $this->flow->held(), $this->icon, Tone::Held);

        return new Flow(
            array_values(array_filter($left, fn (FlowItem $item): bool => $item->count > 0)),
            array_values(array_filter($right, fn (FlowItem $item): bool => $item->count > 0)),
            'shards',
            'Soul shards by the spell that made them, on the left, and by what they were spent on, on the right',
        );
    }

    private function note(): ?string
    {
        $destroyed = $this->destroyed();
        if ($destroyed === 0) {
            return null;
        }
        $stones = $this->flow->bySpells(FlowDirection::Out, [...$this->spells->healthstones->ids(), ...$this->spells->soulstones->ids()]);
        $says = Unit::Count->say($destroyed).' '.($destroyed === 1 ? 'shard was' : 'shards were').' destroyed to make room in the bags';

        return $says.($destroyed > $stones ? ', more than went into Healthstones and Soulstones together.' : '.');
    }
}
