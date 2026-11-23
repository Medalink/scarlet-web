<?php

namespace App\Support\ClassPage\Warlock;

use App\Support\ClassPage\Block;
use App\Support\ClassPage\ClassFacts;
use App\Support\ClassPage\Icon;
use App\Support\ClassPage\Pair;
use App\Support\ClassPage\PairRow;
use App\Support\ClassPage\Split;
use App\Support\ClassPage\SplitPart;
use App\Support\ClassPage\TalentModule;
use App\Support\ClassPage\Tone;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\FlowDirection;
use App\Support\ClassRows\Meter;
use App\Support\ClassRows\SpellTotals;
use App\Support\ClassRows\StateTotals;
use App\Support\ClassRows\Tally;
use App\Support\StateKind;

/** The modules of the warlock talents the latest build holds: Nightfall, Shadowburn, Siphon Life, Fel Domination, Demonic Sacrifice, Soul Link and Conflagrate. */
final readonly class Talents
{
    public function __construct(private ClassRows $rows, private Spells $spells, private Shards $shards) {}

    /** @return list<TalentModule> one for each held talent that has a module, in the build's order */
    public function of(ClassFacts $facts): array
    {
        $modules = [];
        foreach ($facts->talents as $held) {
            $blocks = match ($held->name) {
                'Nightfall' => $this->nightfall(),
                'Shadowburn' => $this->shadowburn(),
                'Siphon Life' => $this->siphon(),
                'Fel Domination' => $this->cast($this->spells->felDomination),
                'Demonic Sacrifice' => $this->cast($this->spells->sacrifice),
                'Soul Link' => $this->link(),
                'Conflagrate' => $this->cast($this->spells->conflagrate),
                default => null,
            };
            if ($blocks === null) {
                continue;
            }
            $modules[] = new TalentModule($held->name, $held->points, $held->max, $blocks['blocks'], $held->spellId === 0 ? null : Icon::spell($held->spellId), $blocks['note']);
        }

        return $modules;
    }

    /** @return array{blocks: list<Block>, note: string|null} */
    private function nightfall(): array
    {
        $trances = $this->rows->states(StateKind::OwnBuff)->of(StateKind::OwnBuff, 'Shadow Trance');
        $procs = array_sum(array_map(fn (StateTotals $state): int => $state->starts, $trances));
        $bolts = $this->rows->tallies()->count(Tally::CastUnder, array_map(fn (StateTotals $state): int => $state->refId, $trances), $this->spells->shadowBolt->ids());
        $cells = [new PairRow('Shadow Trances', $procs)];
        $blocks = [];
        if ($procs > 0) {
            $used = min(100, $bolts / $procs * 100);
            $cells[] = new PairRow('Used', $used, Unit::Percent);
            $blocks[] = self::split($used, 'Spent on an instant Shadow Bolt', 'Ran out');
        }
        $cells[] = new PairRow('Shadow Bolts under it', $bolts);

        return ['blocks' => [new Pair($cells), ...$blocks], 'note' => null];
    }

    /** @return array{blocks: list<Block>, note: string|null} */
    private function shadowburn(): array
    {
        $burn = $this->spells->shadowburn;
        $cells = [new PairRow('Casts', $burn->casts)];
        $blocks = [];
        if ($burn->casts > 0) {
            $share = min(100, $this->rows->tallies()->count(Tally::KillBlow, $burn->ids()) / $burn->casts * 100);
            $cells[] = new PairRow('Killed within 3 seconds', $share, Unit::Percent);
            $blocks[] = self::split($share, 'The target died within 3 seconds', 'It lived');
        }
        if ($this->shards->shared) {
            $cells[] = new PairRow('Shards spent', $this->shards->flow->bySpells(FlowDirection::Out, $burn->ids()));
        }

        return ['blocks' => [new Pair($cells), ...$blocks], 'note' => null];
    }

    /** @return array{blocks: list<Block>, note: string|null} */
    private function siphon(): array
    {
        $meters = $this->rows->meters();
        $share = function (Meter $meter) use ($meters): int|float|null {
            $total = $meters->total($meter);
            if ($total === 0) {
                return null;
            }
            foreach ($meters->spells($meter) as $spell) {
                if ($spell->name === $this->spells->siphon->name) {
                    return min(100, $spell->amount / $total * 100);
                }
            }

            return 0;
        };
        $cells = [new PairRow('Casts', $this->spells->siphon->casts)];
        foreach ([['Of your healing', Meter::Healing], ['Of your damage', Meter::Damage]] as [$label, $meter]) {
            $value = $share($meter);
            if ($value !== null) {
                $cells[] = new PairRow($label, $value, Unit::Percent);
            }
        }

        return [
            'blocks' => [new Pair($cells)],
            'note' => count($cells) === 1 ? null : 'The meter lists the five largest spells of each fight, so these shares count the fights where it listed Siphon Life.',
        ];
    }

    /** @return array{blocks: list<Block>, note: string|null} */
    private function cast(SpellTotals $spell): array
    {
        $hours = $this->rows->playedMs() / 3_600_000;
        $share = $spell->casts === 0 ? 0 : min(100, $spell->fightCasts / $spell->casts * 100);

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

    /** @return array{blocks: list<Block>, note: string|null} */
    private function link(): array
    {
        $up = $this->rows->states()->sum(StateKind::OwnBuff, 'Soul Link');
        $played = $this->rows->playedMs();
        $share = $played === 0 ? 0 : min(100, $up->ms / $played * 100);

        return [
            'blocks' => [
                new Pair([
                    new PairRow('Casts', $this->spells->soulLink->casts),
                    new PairRow('Hours up', $up->hours(), Unit::Hours),
                    new PairRow('Of played time', $share, Unit::Percent),
                ]),
                self::split($share, 'Played with the link up', 'Without'),
            ],
            'note' => null,
        ];
    }

    private static function split(int|float $used, string $yes, string $no): Split
    {
        return new Split([new SplitPart($yes, $used, Tone::Own), new SplitPart($no, 100 - $used, Tone::Lost)]);
    }
}
