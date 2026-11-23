<?php

namespace App\Support\ClassPage\Paladin;

use App\Support\ClassPage\Icon;
use App\Support\ClassPage\Link;
use App\Support\ClassPage\LinkItem;
use App\Support\ClassPage\LinkPair;
use App\Support\ClassPage\Module;
use App\Support\ClassPage\Pair;
use App\Support\ClassPage\PairRow;
use App\Support\ClassPage\Span;
use App\Support\ClassPage\Split;
use App\Support\ClassPage\SplitPart;
use App\Support\ClassPage\Tone;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\Tally;
use App\Support\ClassRows\TallyRow;

/** What became of each seal put up (`seal_fate`): judged, replaced, ran out, still up when the fight ended, or ended another way. */
final readonly class Seals
{
    private const int JUDGED = 1;

    private const int REPLACED = 2;

    private const int RAN_OUT = 3;

    private const int OTHER = 4;

    private const int FIGHT_ENDED = 5;

    private const array FATES = [
        self::JUDGED => ['Judged', Tone::Own],
        self::RAN_OUT => ['Ran out unjudged', Tone::Lost],
        self::REPLACED => ['Replaced by another seal', Tone::Lost],
        self::FIGHT_ENDED => ['Still up when the fight ended', Tone::Held],
        self::OTHER => ['Ended another way', Tone::Lost],
    ];

    /** @var list<TallyRow> */
    private array $fates;

    /** @var array<int, string> */
    private array $names;

    public function __construct(private ClassRows $rows, private Spells $spells)
    {
        $this->fates = $rows->tallies()->of(Tally::SealFate);
        $this->names = $rows->spellNames(array_values(array_unique(array_map(fn (TallyRow $row): int => $row->a, $this->fates))));
    }

    public function put(): int
    {
        return array_sum(array_map(fn (TallyRow $row): int => $row->n, $this->fates));
    }

    public function judged(): int
    {
        return $this->count(self::JUDGED);
    }

    /** Seals judged, as a share of the seals put up, 0 to 100. */
    public function judgedShare(): float
    {
        return $this->put() === 0 ? 0.0 : $this->judged() / $this->put() * 100;
    }

    /**
     * The seals put up of one name, and how many of them were judged.
     *
     * @return array{put: int, judged: int}
     */
    public function of(string $name): array
    {
        $put = $judged = 0;
        foreach ($this->fates as $row) {
            if (($this->names[$row->a] ?? '') === $name) {
                $put += $row->n;
                $judged += $row->b === self::JUDGED ? $row->n : 0;
            }
        }

        return ['put' => $put, 'judged' => $judged];
    }

    public function module(): ?Module
    {
        if ($this->fates === []) {
            return null;
        }
        $bySeal = [];
        $icon = [];
        $best = [];
        foreach ($this->fates as $row) {
            $name = $this->names[$row->a] ?? 'Another seal';
            $bySeal[$name][$row->b] = ($bySeal[$name][$row->b] ?? 0) + $row->n;
            if ($row->n > ($best[$name] ?? 0)) {
                $best[$name] = $row->n;
                $icon[$name] = $row->a;
            }
        }
        uasort($bySeal, fn (array $a, array $b): int => array_sum($b) <=> array_sum($a));
        $left = array_map(fn (string $name): LinkItem => new LinkItem($name, Icon::spell($icon[$name]), Tone::Own), array_keys($bySeal));
        $right = [];
        $pairs = [];
        foreach (self::FATES as $fate => [$label, $tone]) {
            if ($this->count($fate) === 0) {
                continue;
            }
            $right[] = new LinkItem($label, null, $tone);
            foreach (array_values($bySeal) as $at => $fates) {
                if (($fates[$fate] ?? 0) > 0) {
                    $pairs[] = new LinkPair($at, count($right) - 1, $fates[$fate]);
                }
            }
        }
        $ran = $this->count(self::RAN_OUT);
        $replaced = $this->count(self::REPLACED);
        $note = $ran > $replaced ? Unit::Count->say($ran).' seals ran out unjudged, more than the '.Unit::Count->say($replaced).' replaced by another seal.' : null;

        return new Module('From the seal to the judgement', [new Link($left, $right, $pairs, 'seals', 'The seal put up, on the left, and what became of it, on the right, in seals')], Span::W8, $note);
    }

    public function judgement(): ?Module
    {
        $judgement = $this->spells->judgement;
        if ($judgement->casts === 0 && $this->put() === 0) {
            return null;
        }
        $fights = $this->rows->states()->fights()->starts;
        $time = array_sum(array_map(fn (TallyRow $row): int => $row->b === self::JUDGED ? $row->sum : 0, $this->fates));
        $share = $this->judgedShare();

        return new Module('Judgement', [
            new Pair([
                new PairRow('Judgements', $judgement->casts),
                new PairRow('Seconds from seal to judgement', $this->judged() === 0 ? 0 : $time / $this->judged() / 1000, Unit::Decimal, 'average'),
                new PairRow('Judgements a fight', $fights === 0 ? 0 : $judgement->casts / $fights, Unit::Decimal),
            ]),
            new Split([new SplitPart('Seals judged', $share, Tone::Own), new SplitPart('Ran out, replaced or still up', 100 - $share, Tone::Lost)]),
        ], icon: Spells::icon($judgement));
    }

    private function count(int $fate): int
    {
        return array_sum(array_map(fn (TallyRow $row): int => $row->b === $fate ? $row->n : 0, $this->fates));
    }
}
