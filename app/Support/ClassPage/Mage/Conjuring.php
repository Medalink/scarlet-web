<?php

namespace App\Support\ClassPage\Mage;

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
use App\Support\ClassRows\FlowDirection;
use App\Support\ClassRows\FlowPlace;
use App\Support\ClassRows\ItemFlow;
use App\Support\ClassRows\SpellTotals;

/**
 * Where the conjured water and food went, from the `item_flows` rows of those items tied to the spell of each change.
 * The site never receives the bag contents, so what is still in the bags and what vanished at logout cannot be told
 * apart: they are one row, the pool less everything seen leaving.
 */
final readonly class Conjuring
{
    private const array WATER = ['Conjured *Water'];

    private const array FOOD = ['Conjured Bread', 'Conjured Rye', 'Conjured Pumpernickel', 'Conjured Muffin', 'Conjured Sourdough', 'Conjured Sweet Roll', 'Conjured Cinnamon Roll'];

    private const array GEMS = ['Mana Agate', 'Mana Jade', 'Mana Citrine', 'Mana Ruby'];

    private const string NOTE = 'The addon records what goes into and out of the bags, not what they hold, so items left in the bags and items gone at logout are one row.';

    private function __construct(private ItemFlow $water, private ItemFlow $food, private ItemFlow $gems, public ?Icon $icon, private ?Icon $foodIcon, private Spells $spells, private int $playedMs) {}

    public static function of(ClassRows $rows, Spells $spells): self
    {
        $items = $rows->items();
        $water = $items->idsNamed(...self::WATER);
        $food = $items->idsNamed(...self::FOOD);

        return new self(
            $items->named(...self::WATER),
            $items->named(...self::FOOD),
            $items->named(...self::GEMS),
            $water === [] ? null : Icon::item($water[0]),
            $food === [] ? null : Icon::item($food[0]),
            $spells,
            $rows->playedMs(),
        );
    }

    /** Conjured water that came into the bags by the Conjure Water spell. */
    public function waterMade(): int
    {
        return $this->water->bySpells(FlowDirection::In, $this->spells->conjureWater->ids());
    }

    public function foodMade(): int
    {
        return $this->food->bySpells(FlowDirection::In, $this->spells->conjureFood->ids());
    }

    public function made(): int
    {
        return $this->waterMade() + $this->foodMade();
    }

    /** Conjured water and food handed to another player in a trade. */
    public function handedOut(): int
    {
        return $this->water->sum(FlowDirection::Out, FlowPlace::Trade) + $this->food->sum(FlowDirection::Out, FlowPlace::Trade);
    }

    public function handedWater(): int
    {
        return $this->water->sum(FlowDirection::Out, FlowPlace::Trade);
    }

    public function handedFood(): int
    {
        return $this->food->sum(FlowDirection::Out, FlowPlace::Trade);
    }

    public function foodIcon(): ?Icon
    {
        return $this->foodIcon;
    }

    /** Mana gems that left the bags by their own spell: used, not sold, mailed, traded or destroyed. */
    public function gemsUsed(): int
    {
        return $this->gems->sum(FlowDirection::Out, FlowPlace::Other);
    }

    public function row(): ?Row
    {
        if ($this->made() === 0) {
            return null;
        }

        return new Row([new Module('Where conjured water and food went', [$this->chart()], Span::W8, self::NOTE), $this->side()]);
    }

    private function chart(): Flow
    {
        $drunk = $this->out($this->spells->drink);
        $eaten = $this->out($this->spells->food);
        $handed = $this->handedOut();
        $destroyed = $this->water->destroyed() + $this->food->destroyed();
        $out = $this->water->out() + $this->food->out();
        $another = max(0, $out - $drunk - $eaten - $handed - $destroyed);
        $left = [
            new FlowItem('Conjure Water', $this->waterMade(), Spells::icon($this->spells->conjureWater)),
            new FlowItem('Conjure Food', $this->foodMade(), Spells::icon($this->spells->conjureFood)),
        ];
        $right = [
            new FlowItem('Drunk', $drunk, Spells::icon($this->spells->drink)),
            new FlowItem('Eaten', $eaten, Spells::icon($this->spells->food)),
            new FlowItem('Handed to another player', $handed, $this->icon),
            new FlowItem('Destroyed to make room', $destroyed, $this->icon, Tone::Lost),
            new FlowItem('Another way', $another),
            new FlowItem('In the bags or gone at logout', max(0, $this->made() - $out), $this->icon, Tone::Held),
        ];

        return new Flow(
            array_values(array_filter($left, fn (FlowItem $item): bool => $item->count > 0)),
            array_values(array_filter($right, fn (FlowItem $item): bool => $item->count > 0)),
            'items',
            'Conjured water and food by the spell that made it, on the left, and by what became of it, on the right',
        );
    }

    private function side(): Module
    {
        $hours = $this->playedMs / 3_600_000;

        return new Module('Conjuring', [new Pair([
            new PairRow('Conjured a played hour', $hours === 0.0 ? 0 : $this->made() / $hours, Unit::Decimal),
            new PairRow('Mana gems conjured', $this->spells->conjureGem->casts),
            new PairRow('Mana gems used', $this->gemsUsed()),
        ])], icon: Spells::icon($this->spells->conjureWater));
    }

    private function out(SpellTotals $spell): int
    {
        return $this->water->bySpells(FlowDirection::Out, $spell->ids()) + $this->food->bySpells(FlowDirection::Out, $spell->ids());
    }
}
