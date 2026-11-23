<?php

namespace App\Actions\Public\ClassPage;

use App\Contracts\ClassBuilder;
use App\Support\ClassPage\ClassFacts;
use App\Support\ClassPage\ClassParts;
use App\Support\ClassPage\Common\DamageRank;
use App\Support\ClassPage\Common\Sheet;
use App\Support\ClassPage\Figure;
use App\Support\ClassPage\Warrior\Attacks;
use App\Support\ClassPage\Warrior\Damage;
use App\Support\ClassPage\Warrior\Gear;
use App\Support\ClassPage\Warrior\Opens;
use App\Support\ClassPage\Warrior\Party;
use App\Support\ClassPage\Warrior\Shouts;
use App\Support\ClassPage\Warrior\Spells;
use App\Support\ClassPage\Warrior\Stances;
use App\Support\ClassPage\Warrior\Standings;
use App\Support\ClassPage\Warrior\Talents;
use App\Support\ClassRows\ClassRows;

/** The warrior's page: damage dealt and taken, stances, openers, attacks and Execute, shouts, weapons, the talents the build holds and what the warrior does for the party. */
final class WarriorBuilder implements ClassBuilder
{
    public function handle(ClassRows $rows, ClassFacts $facts): ClassParts
    {
        $spells = Spells::of($rows->spells());
        $stances = Stances::of($rows, $spells);
        $attacks = new Attacks($rows, $spells);
        $shouts = new Shouts($rows);
        $gear = Gear::of($rows);
        $damage = new Damage($rows);
        $deaths = $rows->deaths();
        $held = $stances->heldMost();

        return new ClassParts(
            Spells::icon($spells->charges),
            $gear->callouts($attacks),
            [
                new Figure('Charges', $spells->charges->casts, icon: Spells::icon($spells->charges)),
                new Figure('Executes', $spells->execute->casts, icon: Spells::icon($spells->execute)),
                new Figure('Sunder Armors', $spells->sunder->casts, icon: Spells::icon($spells->sunder)),
                new Figure('Battle Shouts', $spells->battleShout->casts, icon: Spells::icon($spells->battleShout)),
                new Figure('Taunts', $spells->taunt->casts, icon: Spells::icon($spells->taunt)),
                new Figure('Deaths', $deaths),
            ],
            [
                new Figure('Charges', $spells->charges->casts, icon: Spells::icon($spells->charges)),
                new Figure('Executes', $spells->execute->casts, icon: Spells::icon($spells->execute)),
                new Figure('Stance held most', $held ?? 'None', icon: Spells::icon($spells->stances)),
                new Figure('Battle Shouts', $spells->battleShout->casts, icon: Spells::icon($spells->battleShout)),
                new Figure('Deaths', $deaths),
            ],
            Sheet::rows([
                [$damage->module(), $stances->split()],
                [$stances->stack(), (new Opens($rows))->module()],
                $attacks->modules(),
                [$shouts->module(), $gear->module(), (new DamageRank($rows))->module()],
            ]),
            (new Talents($rows, $spells))->of($facts),
            (new Party($rows, $spells, $shouts))->rows(),
            (new Standings($rows, $spells, $stances, $shouts, $attacks, $damage))->all(),
        );
    }
}
