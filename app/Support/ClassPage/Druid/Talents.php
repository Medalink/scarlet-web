<?php

namespace App\Support\ClassPage\Druid;

use App\Support\ClassPage\Block;
use App\Support\ClassPage\ClassFacts;
use App\Support\ClassPage\Icon;
use App\Support\ClassPage\Pair;
use App\Support\ClassPage\PairRow;
use App\Support\ClassPage\Rank;
use App\Support\ClassPage\RankRow;
use App\Support\ClassPage\Split;
use App\Support\ClassPage\SplitPart;
use App\Support\ClassPage\TalentModule;
use App\Support\ClassPage\Tone;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\SpellTotals;
use App\Support\ClassRows\StateTotals;
use App\Support\ClassRows\Tally;
use App\Support\StateKind;

/** The modules of the druid talents the latest build holds: Feral Charge, Omen of Clarity, Leader of the Pack, Nature's Grasp, Moonkin Form, Nature's Swiftness and Swiftmend. */
final readonly class Talents
{
    private const int SPENT_ON = 3;

    public function __construct(private ClassRows $rows, private Spells $spells, private Forms $forms) {}

    /** @return list<TalentModule> one for each held talent that has a module, in the build's order */
    public function of(ClassFacts $facts): array
    {
        $modules = [];
        foreach ($facts->talents as $held) {
            $made = match ($held->name) {
                'Feral Charge (Bear)' => $this->charge(),
                'Omen of Clarity' => $this->omen(),
                'Leader of the Pack' => $this->pack(),
                "Nature's Grasp" => $this->cast($this->spells->naturesGrasp),
                'Moonkin Form' => $this->moonkin(),
                "Nature's Swiftness" => $this->swiftness(),
                'Swiftmend' => $this->cast($this->spells->swiftmend),
                default => null,
            };
            if ($made === null) {
                continue;
            }
            $modules[] = new TalentModule($held->name, $held->points, $held->max, $made['blocks'], $held->spellId === 0 ? null : Icon::spell($held->spellId), $made['note'] ?? null);
        }

        return $modules;
    }

    /** @return array{blocks: list<Block>, note: string|null} */
    private function charge(): array
    {
        $charge = $this->spells->feralCharge;
        $fights = $this->rows->states()->fights()->starts;
        $opened = $charge->ids() === [] ? 0 : min($fights, $this->rows->tallies()->count(Tally::FightFirst, $charge->ids()));

        return [
            'blocks' => [
                new Pair([new PairRow('Casts', $charge->casts), new PairRow('Fights it opened', $opened), new PairRow('Share of fights', self::share($opened, $fights), Unit::Percent)]),
                self::split(self::share($opened, $fights), 'Fights it opened', 'Fights it did not'),
            ],
            'note' => 'A fight counts as opened by Feral Charge when it was the first spell cast, from 1.5 seconds before the fight to 3 seconds after its start.',
        ];
    }

    /** @return array{blocks: list<Block>, note: string|null} */
    private function omen(): array
    {
        $auras = $this->clearcasting();
        $procs = array_sum(array_map(fn (StateTotals $state): int => $state->starts, $auras));
        $under = array_sum($this->under($auras));
        $used = min($procs, $under);

        return [
            'blocks' => [
                new Pair([new PairRow('Clearcasting procs', $procs), new PairRow('Used', self::share($used, $procs), Unit::Percent), new PairRow('Ran out', $procs - $used)]),
                self::split(self::share($used, $procs), 'Spent on a cast', 'Ran out'),
            ],
            'note' => null,
        ];
    }

    /** @return array{blocks: list<Block>, note: string|null} */
    private function pack(): array
    {
        $pack = $this->rows->states()->sum(StateKind::OwnBuff, 'Leader of the Pack');
        $feral = $this->forms->ms('bear') + $this->forms->ms('cat');

        return [
            'blocks' => [
                new Pair([
                    new PairRow('Hours up', $pack->hours(), Unit::Hours),
                    new PairRow('Of the time in Bear or Cat Form', self::share($pack->ms, $feral), Unit::Percent),
                    new PairRow('Times it went on', $pack->starts),
                ]),
                self::split(self::share($pack->ms, $feral), 'Time in Bear or Cat Form with the pack up', 'Without'),
            ],
            'note' => null,
        ];
    }

    /** @return array{blocks: list<Block>, note: string|null} */
    private function moonkin(): array
    {
        $moonkin = $this->forms->state('moonkin');
        $share = $moonkin === null ? 0 : self::share($moonkin->fightMs, $moonkin->ms);

        return [
            'blocks' => [
                new Pair([
                    new PairRow('Hours worn', $this->forms->hours('moonkin'), Unit::Hours),
                    new PairRow('Times entered', $moonkin === null ? 0 : $moonkin->starts),
                    new PairRow('In a fight', $share, Unit::Percent),
                ]),
                self::split($share, 'Worn in a fight', 'Worn out of a fight'),
            ],
            'note' => null,
        ];
    }

    /** @return array{blocks: list<Block>, note: string|null} */
    private function swiftness(): array
    {
        $swiftness = $this->spells->naturesSwiftness;
        $under = $this->under($this->auras($swiftness));
        $used = array_sum($under);
        $pair = new Pair([new PairRow('Casts', $swiftness->casts), new PairRow('Spent on a spell', $used), new PairRow('Used', self::share($used, $swiftness->casts), Unit::Percent)]);
        $rank = $this->spentOn($under);

        return ['blocks' => $rank === null ? [$pair] : [$pair, $rank], 'note' => null];
    }

    /** @return array{blocks: list<Block>, note: string|null} */
    private function cast(SpellTotals $spell): array
    {
        $hours = $this->rows->playedMs() / 3_600_000;
        $share = self::share($spell->fightCasts, $spell->casts);

        return [
            'blocks' => [
                new Pair([
                    new PairRow('Casts', $spell->casts),
                    new PairRow('In a fight', $share, Unit::Percent),
                    new PairRow('Hours between casts', $spell->casts === 0 ? 0 : $hours / $spell->casts, Unit::Decimal),
                ]),
                self::split($share, 'Cast in a fight', 'Cast out of a fight'),
            ],
            'note' => null,
        ];
    }

    /** @return list<StateTotals> the Clearcasting auras Omen of Clarity puts on the druid, whoever the client names as their source */
    private function clearcasting(): array
    {
        return [...$this->rows->states()->of(StateKind::OwnBuff, 'Clearcasting'), ...$this->rows->states()->of(StateKind::OthersBuff, 'Clearcasting')];
    }

    /** @return list<StateTotals> */
    private function auras(SpellTotals $spell): array
    {
        return $this->rows->states()->of(StateKind::OwnBuff, $spell->name);
    }

    /**
     * What was cast while one of the auras was on, by the spell's id.
     *
     * @param  list<StateTotals>  $auras
     * @return array<int, int>
     */
    private function under(array $auras): array
    {
        $ids = array_map(fn (StateTotals $state): int => $state->refId, $auras);
        $by = [];
        foreach ($ids === [] ? [] : $this->rows->tallies()->of(Tally::CastUnder) as $row) {
            if (in_array($row->a, $ids, true)) {
                $by[$row->b] = ($by[$row->b] ?? 0) + $row->n;
            }
        }
        arsort($by);

        return $by;
    }

    /** @param  array<int, int>  $under */
    private function spentOn(array $under): ?Rank
    {
        if ($under === []) {
            return null;
        }
        $names = $this->rows->spellNames(array_keys($under));
        $by = [];
        foreach ($under as $id => $count) {
            $name = $names[$id] ?? null;
            if ($name !== null) {
                $by[$name] = ['count' => ($by[$name]['count'] ?? 0) + $count, 'id' => $by[$name]['id'] ?? $id];
            }
        }
        uasort($by, fn (array $a, array $b): int => $b['count'] <=> $a['count']);
        $rows = [];
        foreach (array_slice($by, 0, self::SPENT_ON, true) as $name => $one) {
            $rows[] = new RankRow($name, $one['count'], Icon::spell($one['id']), Tone::Own);
        }

        return $rows === [] ? null : new Rank($rows);
    }

    private static function share(int $part, int $whole): int|float
    {
        return $whole === 0 ? 0 : min(100, $part / $whole * 100);
    }

    private static function split(int|float $share, string $yes, string $no): Split
    {
        return new Split([new SplitPart($yes, $share, Tone::Own), new SplitPart($no, 100 - $share, Tone::Lost)]);
    }
}
