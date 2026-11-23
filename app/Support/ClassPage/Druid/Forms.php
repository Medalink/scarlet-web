<?php

namespace App\Support\ClassPage\Druid;

use App\Support\ClassPage\Callout;
use App\Support\ClassPage\Hue;
use App\Support\ClassPage\Icon;
use App\Support\ClassPage\Link;
use App\Support\ClassPage\LinkItem;
use App\Support\ClassPage\LinkPair;
use App\Support\ClassPage\Module;
use App\Support\ClassPage\Pair;
use App\Support\ClassPage\PairRow;
use App\Support\ClassPage\Rank;
use App\Support\ClassPage\RankRow;
use App\Support\ClassPage\Row;
use App\Support\ClassPage\Series;
use App\Support\ClassPage\Span;
use App\Support\ClassPage\Stack;
use App\Support\ClassPage\StackColumn;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\SpellBook;
use App\Support\ClassRows\StateTotals;
use App\Support\ClassRows\Tally;
use App\Support\StateKind;

/** The form worn: the form auras of `state_times` and the `shift_form` tally, over the time the character played. */
final readonly class Forms
{
    /** Key => the form's name, the client's names of its auras, the spell whose icon stands for it and the colour of its series. */
    public const array ALL = [
        'bear' => ['Bear Form', ['Bear Form', 'Dire Bear Form'], 5487, '#a4662c'],
        'cat' => ['Cat Form', ['Cat Form'], 768, '#d9a336'],
        'travel' => ['Travel Form', ['Travel Form'], 783, '#5da55a'],
        'aquatic' => ['Aquatic Form', ['Aquatic Form'], 1066, '#3f7fae'],
        'moonkin' => ['Moonkin Form', ['Moonkin Form'], 24858, '#8f7be0'],
    ];

    /** The forms that have a model in the head's scene, in the order of their callouts. */
    private const array SCENE = ['bear', 'cat', 'travel', 'aquatic'];

    private const string NONE = 'none';

    private const float RUN_SHARE = 0.5;

    private const int RUN_LEVELS = 2;

    /** @param  array<string, StateTotals>  $forms  the forms that were worn, by key */
    private function __construct(private ClassRows $rows, private array $forms, public StateTotals $mounted, public int $shifts) {}

    public static function of(ClassRows $rows): self
    {
        $forms = [];
        foreach (self::ALL as $key => [, $names]) {
            $worn = $rows->states(StateKind::OwnBuff)->sum(StateKind::OwnBuff, ...$names);
            if ($worn->ms > 0) {
                $forms[$key] = $worn;
            }
        }

        return new self($rows, $forms, $rows->states(StateKind::Mount)->sum(StateKind::Mount), $rows->tallies()->count(Tally::ShiftForm));
    }

    public function state(string $key): ?StateTotals
    {
        return $this->forms[$key] ?? null;
    }

    public function ms(string $key): int
    {
        return isset($this->forms[$key]) ? $this->forms[$key]->ms : 0;
    }

    public function hours(string $key): float
    {
        return $this->ms($key) / 3_600_000;
    }

    /** Milliseconds in any form. */
    public function inFormMs(): int
    {
        return array_sum(array_map(fn (StateTotals $state): int => $state->ms, $this->forms));
    }

    /** Milliseconds played from the first level that has aura rows: the time they can say whether a form was worn. */
    public function playedMs(): int
    {
        return $this->rows->playedMs(StateKind::OwnBuff);
    }

    /** Milliseconds of fighting in a form. */
    public function fightMs(string ...$keys): int
    {
        return array_sum(array_map(fn (string $key): int => isset($this->forms[$key]) ? $this->forms[$key]->fightMs : 0, $keys ?: array_keys($this->forms)));
    }

    /** Milliseconds travelled: Travel Form, Aquatic Form and a mount. */
    public function travelMs(): int
    {
        return $this->ms('travel') + $this->ms('aquatic') + $this->mounted->ms;
    }

    /** The key of the form worn longest, or null when none was. */
    public function top(): ?string
    {
        $top = null;
        foreach ($this->forms as $key => $state) {
            if ($top === null || $state->ms > $this->forms[$top]->ms) {
                $top = $key;
            }
        }

        return $top;
    }

    public static function label(string $key): string
    {
        return self::ALL[$key][0];
    }

    public static function icon(string $key): Icon
    {
        return Icon::spell(self::ALL[$key][2]);
    }

    /** @return list<Callout> */
    public function callouts(): array
    {
        $callouts = [];
        $worn = $this->inFormMs();
        foreach (self::SCENE as $key) {
            $state = $this->forms[$key] ?? null;
            if ($state !== null) {
                $callouts[] = new Callout($key, self::label($key), 'Worn for '.Unit::Hours->say($state->hours()).' hours', Unit::Count->say($state->ms / $worn * 100).'% of time in a form');
            }
        }

        return $callouts;
    }

    public function row(): ?Row
    {
        return $this->forms === [] ? null : new Row([$this->stack(), $this->list()]);
    }

    private function stack(): Module
    {
        $played = $this->rows->playedByLevel(StateKind::OwnBuff);
        $series = [new Series('No form', new Hue('#3d3d46'))];
        foreach (array_keys($this->forms) as $key) {
            $series[] = new Series(self::label($key), new Hue(self::ALL[$key][3]));
        }
        $columns = [];
        $leading = [];
        foreach ($this->rows->levels(StateKind::OwnBuff) as $level) {
            $shares = array_map(fn (StateTotals $state): int => $state->msAt($level), array_values($this->forms));
            $whole = max($played[$level] ?? 0, array_sum($shares));
            if ($whole === 0) {
                continue;
            }
            $columns[] = new StackColumn($level, [($whole - array_sum($shares)) / $whole, ...array_map(fn (int $ms): float => $ms / $whole, $shares)]);
            $leading[$level] = $this->leader($shares, $whole);
        }

        return new Module('The form worn, by level', [new Stack(
            $columns, $series, 'Share of played time each form was worn at each level from '.($columns[0]->label ?? 0).' to '.($columns === [] ? 0 : $columns[count($columns) - 1]->label),
        )], Span::W8, $this->run($leading));
    }

    private function list(): Module
    {
        $rows = [];
        foreach ($this->forms as $key => $state) {
            $rows[] = new RankRow(self::label($key), $state->hours(), self::icon($key), new Hue(self::ALL[$key][3]));
        }
        $none = max(0, $this->playedMs() - $this->inFormMs());
        $rows[] = new RankRow('No form', $none / 3_600_000, tone: new Hue('#5d5d68'));
        $fights = $this->rows->states(StateKind::Fight)->fights()->starts;

        return new Module('Forms', [
            new Rank($rows, Unit::Hours, of: max(array_map(fn (RankRow $row): float|int => $row->value, $rows))),
            new Pair([
                new PairRow('Shapeshifts', $this->shifts),
                new PairRow('Shifts a fight', $fights === 0 ? 0 : $this->shifts / $fights, Unit::Decimal),
            ]),
        ]);
    }

    public function travel(Span $span): ?Module
    {
        if ($this->travelMs() === 0) {
            return null;
        }
        $rows = [];
        foreach (['travel', 'aquatic'] as $key) {
            if ($this->ms($key) > 0) {
                $rows[] = new RankRow(self::label($key), $this->hours($key), self::icon($key), new Hue(self::ALL[$key][3]));
            }
        }
        if ($this->mounted->ms > 0) {
            $rows[] = new RankRow('Mounted', $this->mounted->hours());
        }
        $cells = [new PairRow('Travel Form share of travel', $this->ms('travel') / $this->travelMs() * 100, Unit::Percent)];
        $dash = $this->rows->spells()->sum('Dash', 'Dash')->casts;
        if ($dash > 0) {
            $cells[] = new PairRow('Dash casts', $dash);
        }

        return new Module('Travelling', [new Rank($rows, Unit::Hours, of: max([0, ...array_map(fn (RankRow $row): float|int => $row->value, $rows)])), new Pair($cells)], $span);
    }

    /** From one form to the next, as a link chart: the form each shapeshift left and the form it entered. */
    public function link(Span $span): ?Module
    {
        $names = $this->rows->spellNames($this->linkIds());
        $pairs = [];
        $same = [];
        foreach ($this->rows->tallies()->of(Tally::ShiftForm) as $row) {
            $from = $row->a === 0 ? self::NONE : self::keyOf($names[$row->a] ?? '');
            $to = self::keyOf($names[$row->b] ?? '');
            if ($from === null || $to === null) {
                continue;
            }
            $pairs[$from][$to] = ($pairs[$from][$to] ?? 0) + $row->n;
            if ($from === $to) {
                $same[$to] = ($same[$to] ?? 0) + $row->n;
            }
        }
        if ($pairs === []) {
            return null;
        }
        $left = array_values(array_filter([self::NONE, ...array_keys(self::ALL)], fn (string $key): bool => isset($pairs[$key])));
        $right = array_values(array_filter(array_keys(self::ALL), fn (string $key): bool => array_sum(array_column($pairs, $key)) > 0));
        $links = [];
        foreach ($pairs as $from => $tos) {
            foreach ($tos as $to => $count) {
                $links[] = new LinkPair((int) array_search($from, $left, true), (int) array_search($to, $right, true), $count);
            }
        }
        $item = fn (string $key): LinkItem => $key === self::NONE ? new LinkItem('No form') : new LinkItem(self::label($key), self::icon($key));
        arsort($same);

        return new Module('From one form to the next', [new Link(
            array_map($item, $left), array_map($item, $right), $links, 'shapeshifts', 'The form each shapeshift left, on the left, and the form it entered, on the right',
        )], $span, $this->powershifts($same));
    }

    /** The shifts back into the form just left, as a share of every shift. */
    public function powershiftShare(): float|int
    {
        $same = 0;
        foreach ($this->rows->tallies()->of(Tally::ShiftForm) as $row) {
            if ($row->a !== 0 && $row->a === $row->b) {
                $same += $row->n;
            }
        }

        return $this->shifts === 0 ? 0 : min(100, $same / $this->shifts * 100);
    }

    /** @param  array<string, int>  $same  shifts back into the form just left, by the form */
    private function powershifts(array $same): ?string
    {
        $all = array_sum($same);
        if ($all === 0) {
            return null;
        }
        $key = (string) array_key_first($same);
        $into = $same[$key] === $all ? 'all into '.self::label($key) : Unit::Count->say($same[$key]).' of them into '.self::label($key);

        return Unit::Count->say($all).' shifts went back into the form just left within 2 seconds, '.$into.'.';
    }

    /** @return list<int> */
    private function linkIds(): array
    {
        $ids = [];
        foreach ($this->rows->tallies()->of(Tally::ShiftForm) as $row) {
            array_push($ids, $row->a, $row->b);
        }

        return array_values(array_unique(array_filter($ids, fn (int $id): bool => $id > 0)));
    }

    private static function keyOf(string $name): ?string
    {
        foreach (self::ALL as $key => [, $names]) {
            if (SpellBook::matches($names, $name)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @param  list<int>  $shares  milliseconds in each form worn, in the order of `$forms`
     * @return string|null the key of the form worn for at least half the level
     */
    private function leader(array $shares, int $whole): ?string
    {
        foreach (array_keys($this->forms) as $at => $key) {
            if ($shares[$at] / $whole >= self::RUN_SHARE) {
                return $key;
            }
        }

        return null;
    }

    /** @param  array<int, string|null>  $leading  the form worn for most of each level */
    private function run(array $leading): ?string
    {
        $best = $run = null;
        foreach ($leading + [PHP_INT_MAX => null] as $level => $leader) {
            if ($run !== null && $leader === $run[0] && $level === $run[2] + 1) {
                $run[2] = $level;

                continue;
            }
            if ($run !== null && $run[2] - $run[1] + 1 >= self::RUN_LEVELS && ($best === null || $run[2] - $run[1] > $best[2] - $best[1])) {
                $best = $run;
            }
            $run = $leader === null ? null : [$leader, $level, $level];
        }

        return $best === null ? null : self::label($best[0]).' was worn for most of every level from '.$best[1].' to '.$best[2].'.';
    }
}
