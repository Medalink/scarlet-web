<?php

namespace App\Actions\Public\ClassPage;

use App\Contracts\ClassBuilder;
use App\Support\ClassPage\ClassFacts;
use App\Support\ClassPage\ClassParts;
use App\Support\ClassPage\Common\Sheet;
use App\Support\ClassPage\Figure;
use App\Support\ClassPage\Hunter\Ammo;
use App\Support\ClassPage\Hunter\Field;
use App\Support\ClassPage\Hunter\Party;
use App\Support\ClassPage\Hunter\Pets;
use App\Support\ClassPage\Hunter\Shooting;
use App\Support\ClassPage\Hunter\Spells;
use App\Support\ClassPage\Hunter\Standings;
use App\Support\ClassPage\Hunter\Talents;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;

/** The hunter's page: the ammo and the pet, the aspect and the shots, traps, Feign Death and tracking, the talents the build holds and what the hunter does for the party. */
final class HunterBuilder implements ClassBuilder
{
    public function handle(ClassRows $rows, ClassFacts $facts): ClassParts
    {
        $spells = Spells::of($rows->spells());
        $pets = new Pets($rows, $spells);
        $ammo = new Ammo($rows);
        $shooting = new Shooting($rows);
        $field = new Field($rows, $spells);
        $top = $pets->top();
        $hours = new Figure('Hours with a pet out', $pets->hours(), Unit::Hours);
        $fired = new Figure('Shots fired', $ammo->fired());
        $tamed = new Figure('Tame Beast casts', $spells->tame->casts, icon: Spells::icon($spells->tame));
        $feign = new Figure('Feign Deaths', $spells->feign->casts, icon: Spells::icon($spells->feign));

        return new ClassParts(
            Spells::icon($spells->shots),
            $pets->callouts(),
            [
                $hours,
                $tamed,
                $fired,
                new Figure('Hours of Auto Shot', $shooting->autoShotHours(), Unit::Hours),
                new Figure('Traps set', $spells->traps->casts, icon: Spells::icon($spells->traps)),
                $feign,
            ],
            [
                new Figure('Pet out most', $top === null ? 'None' : ($top->name === '' ? $top->extra : $top->name)),
                $hours,
                $fired,
                $tamed,
                $feign,
            ],
            Sheet::rows([
                [$ammo->chart(), $ammo->quiver()],
                [$pets->stack(), $pets->list()],
                [$shooting->aspects(), $shooting->shots()],
                [$field->traps(), $field->feign(), $field->tracking()],
            ]),
            (new Talents($rows, $spells))->of($facts),
            (new Party($rows, $spells))->rows(),
            (new Standings($rows, $pets, $ammo, $shooting, $field))->all(),
        );
    }
}
