<?php

namespace App\Support\ClassPage;

use App\Support\Feat;
use InvalidArgumentException;

/**
 * A class page as plain data for the app, one case per block type: the same page object the Blade view draws, with
 * numbers as numbers beside their unit's name and its written form, tones and hues by name, and every icon as an
 * absolute address or null. Everything a block holds is written, so a class that brings a new chart needs a case here and
 * nowhere else.
 */
final readonly class ClassPageJson
{
    /** @param  string  $origin  the site's address with no trailing slash, which an icon or link path is joined to */
    public function __construct(private IconMap $icons, private string $origin) {}

    /** @return array<string, mixed> */
    public function page(ClassPage $page): array
    {
        $line = $page->character;

        return [
            'class' => $page->slug(),
            'class_name' => $page->name(),
            'scene' => $page->slug(),
            'url' => $this->link($page->links->classPage),
            'links' => [
                'character' => $this->link($page->links->character),
                'class_page' => $this->link($page->links->classPage),
                'build' => $this->link($page->links->build),
                'battlegrounds' => $this->link($page->links->battlegrounds),
            ],
            'character' => [
                'name' => $line->name,
                'level' => $line->level,
                'hours' => $line->hours,
                'hours_text' => Unit::Hours->say($line->hours),
                'build' => $line->build === null ? null : ['tree' => $line->build->tree, 'points' => $line->build->points],
                'icon' => $this->icon($line->icon),
            ],
            'callouts' => array_map(fn (Callout $callout): array => [
                'key' => $callout->key, 'title' => $callout->title, 'text' => $callout->text, 'note' => $callout->note, 'left' => $callout->left,
            ], $page->callouts),
            'figures' => array_map($this->figure(...), $page->figures),
            'sheet' => array_map($this->row(...), $page->sheet),
            'talents' => array_map(fn (TalentModule $talent): array => [
                'name' => $talent->name,
                'points' => $talent->points,
                'max' => $talent->max,
                'icon' => $this->icon($talent->icon),
                'note' => $talent->note,
                'blocks' => $this->blocks($talent->blocks),
            ], $page->talents),
            'party' => array_map($this->row(...), $page->party),
            'comparison' => $page->comparison === null ? null : $this->comparison($page->comparison),
            'feats' => array_map($this->feat(...), $page->feats),
        ];
    }

    /** @return array<string, mixed> */
    private function figure(Figure $figure): array
    {
        return ['label' => $figure->label, 'of' => $figure->of, 'icon' => $this->icon($figure->icon)] + $this->amount($figure->value, $figure->unit);
    }

    /** @return array<string, mixed> */
    private function row(Row $row): array
    {
        return ['modules' => array_map(fn (Module $module): array => [
            'heading' => $module->heading,
            'span' => $module->span->name,
            'note' => $module->note,
            'icon' => $this->icon($module->icon),
            'blocks' => $this->blocks($module->blocks),
        ], $row->modules)];
    }

    /** @return array<string, mixed> */
    private function comparison(Comparison $comparison): array
    {
        return [
            'of' => $comparison->of,
            'from' => $comparison->from,
            'to' => $comparison->to,
            'characters' => $comparison->characters,
            'standings' => array_map(fn (Standing $standing): array => [
                'label' => $standing->label, 'percent' => $standing->percent, 'icon' => $this->icon($standing->icon),
            ] + $this->amount($standing->value, $standing->unit), $comparison->standings),
        ];
    }

    /** @return array<string, mixed> */
    private function feat(FeatCard $card): array
    {
        return [
            'name' => $card->name,
            'tier' => $card->tier,
            'tier_name' => Feat::TIERS[$card->tier - 1] ?? null,
            'detail' => $card->detail,
            'track' => $card->track,
            'glyph' => $card->glyph,
            'icon' => $this->icon($card->icon),
        ];
    }

    /**
     * @param  list<Block>  $blocks
     * @return list<array<string, mixed>>
     */
    private function blocks(array $blocks): array
    {
        return array_map($this->block(...), $blocks);
    }

    /** @return array<string, mixed> */
    private function block(Block $block): array
    {
        return match (true) {
            $block instanceof Flow => $this->flow($block),
            $block instanceof Link => $this->linkChart($block),
            $block instanceof Mirror => $this->mirror($block),
            $block instanceof Stack => $this->stack($block),
            $block instanceof Bag => $this->bag($block),
            $block instanceof Rank => $this->rank($block),
            $block instanceof Pair => $this->pair($block),
            $block instanceof Split => $this->split($block),
            $block instanceof Titled => $this->titled($block),
            $block instanceof Columns => ['type' => 'columns', 'columns' => array_map($this->titled(...), $block->columns)],
            default => throw new InvalidArgumentException('A class page block of '.$block::class.' has no case in ClassPageJson.'),
        };
    }

    /** @return array<string, mixed> */
    private function flow(Flow $flow): array
    {
        $item = fn (FlowItem $item): array => [
            'label' => $item->label, 'count' => $item->count, 'icon' => $this->icon($item->icon), 'tone' => $item->tone->value,
        ];

        return [
            'type' => 'flow',
            'unit' => $flow->unit,
            'label' => $flow->label,
            'left' => array_map($item, $flow->left),
            'right' => array_map($item, $flow->right),
        ];
    }

    /** @return array<string, mixed> */
    private function linkChart(Link $link): array
    {
        $item = fn (LinkItem $item): array => ['label' => $item->label, 'icon' => $this->icon($item->icon), 'tone' => $item->tone->value];

        return [
            'type' => 'link',
            'unit' => $link->unit,
            'label' => $link->label,
            'left' => array_map($item, $link->left),
            'right' => array_map($item, $link->right),
            'pairs' => array_map(fn (LinkPair $pair): array => ['left' => $pair->left, 'right' => $pair->right, 'count' => $pair->count], $link->pairs),
        ];
    }

    /** @return array<string, mixed> */
    private function mirror(Mirror $mirror): array
    {
        return [
            'type' => 'mirror',
            'label' => $mirror->label,
            'unit' => $this->unit($mirror->unit),
            'up' => $this->series($mirror->up),
            'down' => $this->series($mirror->down),
            'points' => array_map(fn (MirrorPoint $point): array => ['level' => $point->level, 'up' => $point->up, 'down' => $point->down], $mirror->points),
        ];
    }

    /** @return array<string, mixed> */
    private function stack(Stack $stack): array
    {
        return [
            'type' => 'stack',
            'label' => $stack->label,
            'series' => array_map($this->series(...), $stack->series),
            'columns' => array_map(fn (StackColumn $column): array => ['label' => $column->label, 'shares' => $column->shares], $stack->columns),
        ];
    }

    /** @return array<string, mixed> */
    private function bag(Bag $bag): array
    {
        return [
            'type' => 'bag',
            'noun' => $bag->noun,
            'slots' => $bag->slots,
            'items' => array_map(fn (BagItem $item): array => ['icon' => $this->icon($item->icon), 'slots' => $item->slots], $bag->items),
        ];
    }

    /** @return array<string, mixed> */
    private function rank(Rank $rank): array
    {
        return [
            'type' => 'rank',
            'unit' => $this->unit($rank->unit),
            'plain' => $rank->plain,
            'of' => $rank->of,
            'rows' => array_map(fn (RankRow $row): array => [
                'label' => $row->label,
                'aside' => $row->aside,
                'value' => $row->value,
                'text' => $rank->unit->row($row->value),
                'icon' => $this->icon($row->icon),
                'share' => $row->share,
            ] + $this->color($row->tone), $rank->rows),
        ];
    }

    /** @return array<string, mixed> */
    private function pair(Pair $pair): array
    {
        return [
            'type' => 'pair',
            'rows' => array_map(fn (PairRow $row): array => ['label' => $row->label, 'small' => $row->small] + $this->amount($row->value, $row->unit), $pair->rows),
        ];
    }

    /** @return array<string, mixed> */
    private function split(Split $split): array
    {
        return [
            'type' => 'split',
            'parts' => array_map(fn (SplitPart $part): array => ['label' => $part->label, 'value' => $part->value] + $this->color($part->tone), $split->parts),
        ];
    }

    /** @return array<string, mixed> */
    private function titled(Titled $titled): array
    {
        return ['type' => 'titled', 'title' => $titled->title, 'icon' => $this->icon($titled->icon), 'blocks' => $this->blocks($titled->blocks)];
    }

    /** @return array{label: string, hue: string} */
    private function series(Series $series): array
    {
        return ['label' => $series->label, 'hue' => $series->hue->css];
    }

    /** @return array{tone: string|null, hue: string|null} a tone by name, or a hue as the CSS colour it holds */
    private function color(Tone|Hue|null $color): array
    {
        return ['tone' => $color instanceof Tone ? $color->value : null, 'hue' => $color instanceof Hue ? $color->css : null];
    }

    /** @return array{value: int|float|string, unit: string, text: string} */
    private function amount(int|float|string $value, Unit $unit): array
    {
        return ['value' => $value, 'unit' => $this->unit($unit), 'text' => $unit->figure($value)];
    }

    private function unit(Unit $unit): string
    {
        return match ($unit) {
            Unit::Count => 'count',
            Unit::Short => 'short',
            Unit::Decimal => 'decimal',
            Unit::Percent => 'percent',
            Unit::PercentTenth => 'percent_tenth',
            Unit::Hours => 'hours',
        };
    }

    private function link(?string $path): ?string
    {
        return $path === null ? null : $this->origin.$path;
    }

    private function icon(?Icon $icon): ?string
    {
        return $this->link($this->icons->url($icon));
    }
}
