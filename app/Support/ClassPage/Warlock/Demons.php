<?php

namespace App\Support\ClassPage\Warlock;

use App\Support\ClassPage\Callout;
use App\Support\ClassPage\Hue;
use App\Support\ClassPage\Icon;
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
use App\Support\ClassRows\StateTotals;
use App\Support\StateKind;

/** The demon that was out: the `p` spans of `state_times`, by the pet's family and by level, over the time the character played. */
final readonly class Demons
{
    /** Family => summon spell whose icon stands for it, the colour of its series and the key of its prop in the head's scene. */
    private const array FAMILIES = [
        'Imp' => [688, '#d9a336'],
        'Voidwalker' => [697, 'var(--class)'],
        'Succubus' => [712, 'var(--soft)'],
        'Felhunter' => [691, '#2fb3b3'],
    ];

    /** Families whose callouts stand on the left of their model. */
    private const array LEFT = ['Imp'];

    /** The order of the callouts on the head. */
    private const array CALLOUTS = ['Voidwalker', 'Succubus', 'Imp', 'Felhunter'];

    private const float RUN_SHARE = 0.5;

    private const int RUN_LEVELS = 2;

    /**
     * @param  array<string, StateTotals>  $families  by the pet's family
     * @param  array<string, string>  $pets  the name the character gave the demon of each family it kept longest
     */
    private function __construct(private ClassRows $rows, private array $families, private array $pets, private int $others, private Spells $spells, private bool $shared) {}

    public static function of(ClassRows $rows, Spells $spells): self
    {
        $by = $pets = $longest = [];
        $spans = $rows->states(StateKind::Pet)->of(StateKind::Pet);
        foreach ($spans as $pet) {
            if ($pet->name === '' && $pet->extra === '') {
                continue;
            }
            $family = $pet->extra;
            $by[$family][] = $pet;
            if ($pet->ms >= ($longest[$family] ?? -1)) {
                $longest[$family] = $pet->ms;
                $pets[$family] = $pet->name;
            }
        }
        $families = [];
        $others = 0;
        foreach ($by as $family => $list) {
            $merged = self::merge($list);
            if (isset(self::FAMILIES[$family])) {
                $families[$family] = $merged;

                continue;
            }
            $others += $merged->ms;
        }

        return new self($rows, $families, $pets, $others, $spells, $spans !== []);
    }

    /** Whether the character shares any pet rows at all; without them every demon figure is withheld, not 0. */
    public function shared(): bool
    {
        return $this->shared;
    }

    /** Milliseconds a demon of any family was out. */
    public function withMs(): int
    {
        return array_sum(array_map(fn (StateTotals $state): int => $state->ms, $this->families)) + $this->others;
    }

    public function withHours(): float
    {
        return $this->withMs() / 3_600_000;
    }

    /** Milliseconds played from the first level that has pet rows: the time the rows can say whether a demon was out. */
    public function playedMs(): int
    {
        return $this->rows->playedMs(StateKind::Pet);
    }

    public function playedHours(): float
    {
        return $this->playedMs() / 3_600_000;
    }

    /** The family that was out longest, or null when none was. */
    public function top(): ?string
    {
        $top = null;
        foreach ($this->families as $family => $state) {
            if ($top === null || $state->ms > $this->families[$top]->ms) {
                $top = $family;
            }
        }

        return $top;
    }

    public function icon(?string $family): ?Icon
    {
        return $family === null ? null : Icon::spell(self::FAMILIES[$family][0]);
    }

    /** @return list<Callout> */
    public function callouts(): array
    {
        $callouts = [];
        $total = $this->withMs();
        foreach (self::CALLOUTS as $family) {
            $state = $this->families[$family] ?? null;
            if ($state === null || $state->ms === 0) {
                continue;
            }
            $pet = $this->pets[$family] ?? '';
            $out = 'out '.Unit::Hours->say($state->hours()).' hours';
            $callouts[] = new Callout(
                strtolower($family), $pet === '' ? $family : $pet, $pet === '' ? ucfirst($out) : $family.', '.$out,
                Unit::Count->say($state->ms / $total * 100).'% of demon time', in_array($family, self::LEFT, true),
            );
        }

        return $callouts;
    }

    public function row(): ?Row
    {
        if ($this->withMs() === 0) {
            return null;
        }

        return new Row([$this->stack(), $this->list()]);
    }

    private function stack(): Module
    {
        $played = $this->rows->playedByLevel(StateKind::Pet);
        $series = [];
        foreach (self::FAMILIES as $family => [, $hue]) {
            $series[] = new Series($family, new Hue($hue));
        }
        if ($this->others > 0) {
            $series[] = new Series('Another demon', new Hue('#8b8b95'));
        }
        $series[] = new Series('No demon out', new Hue('#3d3d46'));
        $columns = [];
        $leading = [];
        foreach ($this->rows->levels(StateKind::Pet) as $level) {
            $time = $played[$level] ?? 0;
            $shares = [];
            foreach (array_keys(self::FAMILIES) as $family) {
                $shares[] = $this->families[$family]->byLevel[$level]['ms'] ?? 0;
            }
            if ($this->others > 0) {
                $shares[] = $this->othersAt($level);
            }
            $out = array_sum($shares);
            $whole = max($time, $out);
            if ($whole === 0) {
                continue;
            }
            $columns[] = new StackColumn($level, [...array_map(fn (int $ms): float => $ms / $whole, $shares), ($whole - $out) / $whole]);
            $leading[$level] = $this->leader($shares, $whole);
        }

        return new Module('The demon that was out, by level', [new Stack(
            $columns, $series, 'Share of played time each demon was out at each level from '.($columns[0]->label ?? 0).' to '.($columns === [] ? 0 : $columns[count($columns) - 1]->label),
        )], Span::W8, $this->run($leading));
    }

    private function list(): Module
    {
        $rows = [];
        foreach (self::FAMILIES as $family => [$spell, $hue]) {
            $state = $this->families[$family] ?? null;
            if ($state !== null) {
                $rows[] = new RankRow($family, $state->hours(), Icon::spell($spell), new Hue($hue), $this->pets[$family] ?? null);
            }
        }
        if ($this->others > 0) {
            $rows[] = new RankRow('Another demon', $this->others / 3_600_000, tone: new Hue('#8b8b95'));
        }
        $none = max(0, $this->playedMs() - $this->withMs());
        $rows[] = new RankRow('No demon out', $none / 3_600_000, tone: new Hue('#5d5d68'));

        return new Module('Demons', [
            new Rank($rows, Unit::Hours, of: max(array_map(fn (RankRow $row): float|int => $row->value, $rows))),
            new Pair([
                new PairRow('Summons', $this->spells->summons->casts),
                new PairRow('Health Funnel', Unit::Hours->say($this->spells->funnel->channelMs / 3_600_000).' h'),
                new PairRow('Sacrificed', $this->spells->sacrifice->casts),
            ]),
        ]);
    }

    private function othersAt(int $level): int
    {
        $ms = 0;
        foreach ($this->rows->states(StateKind::Pet)->of(StateKind::Pet) as $pet) {
            if (! isset(self::FAMILIES[$pet->extra]) && ($pet->name !== '' || $pet->extra !== '')) {
                $ms += $pet->byLevel[$level]['ms'] ?? 0;
            }
        }

        return $ms;
    }

    /**
     * @param  list<int>  $shares  milliseconds by family, in the order of `FAMILIES`
     * @return string|null the family out for at least half the level
     */
    private function leader(array $shares, int $whole): ?string
    {
        $names = array_keys(self::FAMILIES);
        foreach ($names as $i => $family) {
            if ($shares[$i] / $whole >= self::RUN_SHARE) {
                return $family;
            }
        }

        return null;
    }

    /** @param  array<int, string|null>  $leading  the family out for most of each level */
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

        return $best === null ? null : 'The '.$best[0].' was out for most of every level from '.$best[1].' to '.$best[2].'.';
    }

    /** @param  list<StateTotals>  $list */
    private static function merge(array $list): StateTotals
    {
        $ms = $starts = $fight = 0;
        $levels = [];
        foreach ($list as $state) {
            $ms += $state->ms;
            $starts += $state->starts;
            $fight += $state->fightMs;
            foreach ($state->byLevel as $level => $row) {
                $at = $levels[$level] ?? ['starts' => 0, 'ms' => 0, 'fightMs' => 0];
                $levels[$level] = ['starts' => $at['starts'] + $row['starts'], 'ms' => $at['ms'] + $row['ms'], 'fightMs' => $at['fightMs'] + $row['fightMs']];
            }
        }
        ksort($levels);

        return new StateTotals(0, '', $list[0]->extra, $starts, $ms, $fight, $levels);
    }
}
