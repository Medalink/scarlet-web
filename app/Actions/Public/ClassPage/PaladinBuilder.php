<?php

namespace App\Actions\Public\ClassPage;

use App\Contracts\ClassBuilder;
use App\Support\ClassPage\ClassFacts;
use App\Support\ClassPage\ClassParts;
use App\Support\ClassPage\Common\Sheet;
use App\Support\ClassPage\Figure;
use App\Support\ClassPage\Paladin\Auras;
use App\Support\ClassPage\Paladin\Blessings;
use App\Support\ClassPage\Paladin\Combat;
use App\Support\ClassPage\Paladin\Head;
use App\Support\ClassPage\Paladin\Party;
use App\Support\ClassPage\Paladin\Seals;
use App\Support\ClassPage\Paladin\Spells;
use App\Support\ClassPage\Paladin\Standings;
use App\Support\ClassPage\Paladin\Talents;
use App\Support\ClassRows\ClassRows;

/** The paladin's page: the seal to the judgement, the aura, blessings, the undead, the bubble, healing and control, the talents the build holds and what the paladin does for the party. */
final class PaladinBuilder implements ClassBuilder
{
    public function handle(ClassRows $rows, ClassFacts $facts): ClassParts
    {
        $spells = Spells::of($rows->spells());
        $seals = new Seals($rows, $spells);
        $blessings = new Blessings($rows, $spells);
        $combat = new Combat($rows, $spells);
        $top = $blessings->top();
        $judgement = Spells::icon($spells->judgement);
        $raised = new Figure('Players raised', $spells->redemption->casts, icon: Spells::icon($spells->redemption));
        $shields = new Figure('Divine Shields', $spells->divineShield->casts, icon: Spells::icon($spells->divineShield));

        return new ClassParts(
            $judgement,
            (new Head($rows, $spells, $blessings, $combat))->callouts(),
            [
                new Figure('Judgements', $spells->judgement->casts, icon: $judgement),
                new Figure('Blessings cast', $blessings->total(), icon: $top === null ? null : Spells::icon($top)),
                new Figure('Lay on Hands', $spells->layOnHands->casts, icon: Spells::icon($spells->layOnHands)),
                $shields,
                new Figure('Hammers of Justice', $spells->hammer->casts, icon: Spells::icon($spells->hammer)),
                $raised,
            ],
            [
                new Figure('Judgements', $spells->judgement->casts, icon: $judgement),
                new Figure('Blessings cast', $blessings->total(), icon: $top === null ? null : Spells::icon($top)),
                new Figure('Blessing cast most', $top === null ? 'None' : $top->name, icon: $top === null ? null : Spells::icon($top)),
                $shields,
                $raised,
            ],
            Sheet::rows([
                [$seals->module(), $seals->judgement()],
                [(new Auras($rows))->module(), $blessings->module()],
                [$combat->undead(), $combat->bubble()],
                [$combat->layOnHands(), $combat->heals(), $combat->control()],
            ]),
            (new Talents($rows, $spells, $seals))->of($facts),
            (new Party($rows, $spells, $blessings))->rows(),
            (new Standings($rows, $spells, $seals, $combat))->all(),
        );
    }
}
