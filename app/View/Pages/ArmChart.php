<?php

namespace App\View\Pages;

use App\Support\LootArm;
use App\Support\LootRule;
use App\Support\RateDifference;
use App\Support\RateEstimate;
use App\Support\RateFlag;
use App\Support\RateSample;

/**
 * The chart of drop rate by loot rule and by group size on a creature or object page (docs/comps/creature.html), from the
 * answer of `SourceRuleArms`: a row for each item, a mark for each arm, and the verdict on the two arms compared. `x-arms`
 * draws it.
 */
final class ArmChart
{
    /**
     * @param  array<string, mixed>  $answer  the answer of SourceRuleArms over loot rules
     * @param  string  $groups  the address of the groups page narrowed to this source
     * @return array<string, mixed>|null null when the source has no windows to split
     */
    public static function rules(array $answer, string $groups): ?array
    {
        /** @var list<array{rule: string, windows: int}> $rules */
        $rules = $answer['rules'];
        if ($rules === []) {
            return null;
        }
        /** @var list<LootArm> $arms */
        $arms = $answer['arms'];
        $drawn = array_values(array_filter($arms, fn (LootArm $arm): bool => $arm->drawn()));
        $words = fn (array $seen): string => strtolower(LootRule::words($seen['rule']));
        $empty = match (true) {
            count($rules) === 1 => 'Only '.$words($rules[0]).' recorded.',
            count($drawn) === 1 => 'Only '.strtolower($drawn[0]->label).' has 10 corpses or more; '.self::sentence(array_map(fn (array $seen): string => $words($seen).' with '.$seen['windows'], array_values(array_filter($rules, fn (array $seen): bool => $seen['rule'] !== $drawn[0]->key)))).' are not drawn.',
            count($drawn) === 0 => 'No loot rule has 10 corpses yet: '.self::sentence(array_map(fn (array $seen): string => $words($seen).' with '.$seen['windows'], $rules)).'.',
            default => null,
        };

        return ['heading' => 'Drop rate by loot rule', 'groups' => $groups] + self::chart($answer, $empty, self::undrawn($arms, fn (LootArm $arm): string => strtolower($arm->label).' with '.$arm->windows));
    }

    /**
     * @param  array<string, mixed>  $answer  the answer of SourceRuleArms over group sizes
     * @return array<string, mixed>|null null when the source has no windows under a rule
     */
    public static function sizes(array $answer): ?array
    {
        /** @var list<array{size: int, windows: int}> $sizes */
        $sizes = $answer['sizes'];
        /** @var string|null $rule */
        $rule = $answer['rule'];
        if ($rule === null || $sizes === []) {
            return null;
        }
        /** @var list<LootArm> $arms */
        $arms = $answer['arms'];
        $drawn = array_values(array_filter($arms, fn (LootArm $arm): bool => $arm->drawn()));
        $listed = self::sentence(array_map(fn (array $seen, int $position): string => ($position === 0 ? 'size ' : '').$seen['size'].(count($sizes) === 1 ? '' : ' ('.$seen['windows'].')'), $sizes, array_keys($sizes)));
        $empty = count($drawn) < 2 ? LootRule::words($rule).' recorded at '.$listed.(count($sizes) === 1 ? ' only.' : '.') : null;

        return ['heading' => 'Drop rate by group size under '.strtolower(LootRule::words($rule))] + self::chart($answer, $empty, self::undrawn($arms, fn (LootArm $arm): string => 'size '.$arm->key.' with '.$arm->windows));
    }

    /**
     * @param  array<string, mixed>  $answer
     * @return array{empty: string|null, rows: list<array<string, mixed>>, note: string|null, groups: string|null}
     */
    private static function chart(array $answer, ?string $empty, ?string $undrawn): array
    {
        /** @var list<array<string, mixed>> $source */
        $source = $answer['rows'];
        /** @var list<LootArm> $pair */
        $pair = $answer['pair'];
        /** @var list<LootArm> $arms */
        $arms = $answer['arms'];
        $drawn = count(array_filter($arms, fn (LootArm $arm): bool => $arm->drawn()));
        if ($empty === null && $source === []) {
            $empty = 'No item has 10 drops yet.';
        }
        $hollow = array_filter($arms, fn (LootArm $arm): bool => $arm->drawn() && $arm->windows < RateSample::SOLID_FROM) === []
            ? null
            : 'Hollow: '.RateSample::HOLLOW_FROM.' to '.(RateSample::SOLID_FROM - 1).' corpses.';
        $key = implode(' ', array_filter([$hollow, $undrawn]));

        return ['empty' => $empty, 'rows' => array_map(fn (array $row): array => self::row($row, $pair, $drawn), $source), 'note' => $source === [] || $key === '' ? null : $key, 'groups' => null];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<LootArm>  $pair
     * @return array<string, mixed>
     */
    private static function row(array $row, array $pair, int $drawn): array
    {
        /** @var list<array{arm: LootArm, estimate: RateEstimate}> $cells */
        $cells = $row['cells'];
        /** @var RateDifference|null $difference */
        $difference = $row['difference'];
        $quest = in_array(RateFlag::QuestItem, $cells[0]['estimate']->flags, true);

        return [
            'name' => (string) ($row['item'] ?? 'Item '.$row['item_id']), 'href' => '/items/'.$row['item_id'], 'tag' => $quest ? RateFlag::QuestItem->label() : null,
            'arms' => array_map(fn (array $cell): array => [
                'label' => $cell['arm']->label, 'estimate' => $cell['estimate'],
                'tags' => array_map(fn (RateFlag $flag): string => $flag->label(), array_values(array_filter($cell['estimate']->flags, fn (RateFlag $flag): bool => $flag !== RateFlag::QuestItem))),
            ], $cells),
            'verdict' => $difference === null ? null : self::verdict($difference, $pair, $cells, $drawn > 2),
        ];
    }

    /**
     * @param  list<LootArm>  $pair
     * @param  list<array{arm: LootArm, estimate: RateEstimate}>  $cells
     * @return array{text: string, marked: bool, tags: list<array{arm: string, tags: list<string>}>}
     */
    private static function verdict(RateDifference $difference, array $pair, array $cells, bool $named): array
    {
        $prefix = $named ? $pair[0]->label.' and '.$pair[1]->label.': ' : '';
        if (! $difference->marked) {
            return ['text' => $prefix.'no difference found', 'marked' => false, 'tags' => []];
        }
        $tags = [];
        foreach ($cells as $cell) {
            $armTags = array_map(fn (RateFlag $flag): string => $flag->label(), array_values(array_filter($cell['estimate']->flags, fn (RateFlag $flag): bool => $flag !== RateFlag::QuestItem)));
            if (in_array($cell['arm']->key, [$pair[0]->key, $pair[1]->key], true) && $armTags !== []) {
                $tags[] = ['arm' => $cell['arm']->label, 'tags' => $armTags];
            }
        }
        if (in_array(RateFlag::DifferentCharacters, $difference->flags, true)) {
            $tags[] = ['arm' => '', 'tags' => [RateFlag::DifferentCharacters->label()]];
        }

        return ['text' => $pair[1]->label.' '.$difference->words().', '.$difference->range(), 'marked' => true, 'tags' => $tags];
    }

    /**
     * @param  list<LootArm>  $arms
     * @param  callable(LootArm): string  $say
     */
    private static function undrawn(array $arms, callable $say): ?string
    {
        $few = array_values(array_filter($arms, fn (LootArm $arm): bool => ! $arm->drawn()));

        return $few === [] ? null : 'Not drawn, under 10 corpses: '.self::sentence(array_map($say, $few)).'.';
    }

    /**
     * @param  list<string>  $parts
     */
    private static function sentence(array $parts): string
    {
        return count($parts) < 2 ? implode('', $parts) : implode(', ', array_slice($parts, 0, -1)).' and '.$parts[count($parts) - 1];
    }
}
