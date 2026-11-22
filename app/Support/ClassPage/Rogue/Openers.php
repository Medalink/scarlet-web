<?php

namespace App\Support\ClassPage\Rogue;

use App\Support\ClassPage\Icon;
use App\Support\ClassPage\Link;
use App\Support\ClassPage\LinkItem;
use App\Support\ClassPage\LinkPair;
use App\Support\ClassPage\Module;
use App\Support\ClassPage\Span;
use App\Support\ClassPage\Tone;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\Tally;

/** How each fight began and the first finisher of it: the `open_finish` tally, with each spell named by the client. */
final readonly class Openers
{
    private const string NO_OPENER = 'No opener';

    private const string NO_FINISHER = 'No finisher';

    /**
     * @param  array<string, array<string, int>>  $fights  fights by opener name, then finisher name; '' for none
     * @param  array<string, int>  $spells  the spell id to draw the icon of, by name
     */
    private function __construct(private array $fights, private array $spells) {}

    public static function of(ClassRows $rows): self
    {
        $tally = $rows->tallies()->of(Tally::OpenFinish);
        $ids = [];
        foreach ($tally as $row) {
            array_push($ids, $row->a, $row->b);
        }
        $names = $rows->spellNames(array_values(array_filter(array_unique($ids), fn (int $id): bool => $id > 0)));
        $fights = $spells = [];
        foreach ($tally as $row) {
            $opener = $names[$row->a] ?? '';
            $finisher = $names[$row->b] ?? '';
            if (($row->a > 0 && ! in_array($opener, Spells::OPENERS, true)) || ($row->b > 0 && ! array_key_exists($finisher, Spells::FINISHERS))) {
                continue;
            }
            $fights[$opener][$finisher] = ($fights[$opener][$finisher] ?? 0) + $row->n;
            foreach ([[$opener, $row->a], [$finisher, $row->b]] as [$name, $id]) {
                if ($name !== '') {
                    $spells[$name] ??= $id;
                }
            }
        }

        return new self($fights, $spells);
    }

    public function module(): ?Module
    {
        $left = array_values(array_filter([...Spells::OPENERS, ''], fn (string $name): bool => isset($this->fights[$name])));
        $right = array_values(array_filter([...array_keys(Spells::FINISHERS), ''], fn (string $name): bool => $this->finishedWith($name) > 0));
        if ($left === [] || $right === []) {
            return null;
        }
        $pairs = [];
        foreach ($left as $i => $opener) {
            foreach ($right as $j => $finisher) {
                $pairs[] = new LinkPair($i, $j, $this->fights[$opener][$finisher] ?? 0);
            }
        }
        $link = new Link(
            array_map(fn (string $name): LinkItem => new LinkItem($name === '' ? self::NO_OPENER : $name, $this->icon($name)), $left),
            array_map(fn (string $name): LinkItem => new LinkItem($name === '' ? self::NO_FINISHER : $name, $this->icon($name), $name === '' ? Tone::Lost : Tone::Own), $right),
            $pairs,
            'fights',
            'Fights by how they opened, on the left, and by the first finisher used in them, on the right',
        );

        return new Module('From the opener to the finisher', [$link], Span::W8, $this->note());
    }

    /** Fights that had a named opener. */
    public function opened(): int
    {
        return array_sum(array_map(fn (string $opener): int => $this->openedWith($opener), Spells::OPENERS));
    }

    public function openedWith(string $opener): int
    {
        return array_sum($this->fights[$opener] ?? []);
    }

    public function finishedWith(string $finisher): int
    {
        return array_sum(array_map(fn (array $row): int => $row[$finisher] ?? 0, $this->fights));
    }

    private function icon(string $name): ?Icon
    {
        return isset($this->spells[$name]) ? Icon::spell($this->spells[$name]) : null;
    }

    private function note(): ?string
    {
        $top = null;
        foreach (Spells::OPENERS as $opener) {
            if ($this->openedWith($opener) > ($top === null ? 0 : $this->openedWith($top))) {
                $top = $opener;
            }
        }
        $none = $this->finishedWith('');
        $says = [];
        if ($top !== null) {
            $led = $this->fights[$top];
            unset($led['']);
            arsort($led);
            $next = array_key_first($led);
            $says[] = $top.' opened '.Unit::Count->say($this->openedWith($top)).' fights'.($next === null ? '.' : ' and led into '.$next.' in '.Unit::Count->say($led[$next]).' of them.');
        }
        if ($none > 0) {
            $says[] = Unit::Count->say($none).' fights ended without a finisher.';
        }

        return $says === [] ? null : implode(' ', $says);
    }
}
