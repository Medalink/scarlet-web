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
use App\Support\ClassRows\StateTotals;
use App\Support\ClassRows\Tally;
use App\Support\StateKind;

/** The modules of the warlock talents the latest build holds: Nightfall, Shadowburn and Siphon Life. */
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
        $used = $procs === 0 ? 0 : min(100, $bolts / $procs * 100);
        $blocks = [new Pair([new PairRow('Shadow Trances', $procs), new PairRow('Used', $used, Unit::Percent), new PairRow('Shadow Bolts under it', $bolts)])];
        if ($procs > 0) {
            $blocks[] = self::split($used, 'Spent on an instant Shadow Bolt', 'Ran out');
        }

        return ['blocks' => $blocks, 'note' => null];
    }

    /** @return array{blocks: list<Block>, note: string|null} */
    private function shadowburn(): array
    {
        $burn = $this->spells->shadowburn;
        $killed = $this->rows->tallies()->count(Tally::KillBlow, $burn->ids());
        $share = $burn->casts === 0 ? 0 : min(100, $killed / $burn->casts * 100);
        $blocks = [new Pair([
            new PairRow('Casts', $burn->casts),
            new PairRow('Killed within 3 seconds', $share, Unit::Percent),
            new PairRow('Shards spent', $this->shards->flow->bySpells(FlowDirection::Out, $burn->ids())),
        ])];
        if ($burn->casts > 0) {
            $blocks[] = self::split($share, 'The target died within 3 seconds', 'It lived');
        }

        return ['blocks' => $blocks, 'note' => null];
    }

    /** @return array{blocks: list<Block>, note: string|null} */
    private function siphon(): array
    {
        $meters = $this->rows->meters();
        $share = function (Meter $meter) use ($meters): int|float {
            $total = $meters->total($meter);
            foreach ($meters->spells($meter) as $spell) {
                if ($spell->name === $this->spells->siphon->name) {
                    return $total === 0 ? 0 : min(100, $spell->amount / $total * 100);
                }
            }

            return 0;
        };

        return [
            'blocks' => [new Pair([
                new PairRow('Casts', $this->spells->siphon->casts),
                new PairRow('Of your healing', $share(Meter::Healing), Unit::Percent),
                new PairRow('Of your damage', $share(Meter::Damage), Unit::Percent),
            ])],
            'note' => 'The meter lists the five largest spells of each fight, so these shares count the fights where it listed Siphon Life.',
        ];
    }

    private static function split(int|float $used, string $yes, string $no): Split
    {
        return new Split([new SplitPart($yes, $used, Tone::Own), new SplitPart($no, 100 - $used, Tone::Lost)]);
    }
}
