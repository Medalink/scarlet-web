<?php

namespace App\Actions\Public\ClassPage;

use App\Contracts\ClassBuilder;
use App\Support\ClassPage\Callout;
use App\Support\ClassPage\Casts;
use App\Support\ClassPage\ClassFacts;
use App\Support\ClassPage\ClassParts;
use App\Support\ClassPage\Figure;
use App\Support\ClassPage\Module;
use App\Support\ClassPage\Priest\Healing;
use App\Support\ClassPage\Priest\Party;
use App\Support\ClassPage\Priest\Shadow;
use App\Support\ClassPage\Priest\Spells;
use App\Support\ClassPage\Priest\Standings;
use App\Support\ClassPage\Priest\Talents;
use App\Support\ClassPage\Priest\Upkeep;
use App\Support\ClassPage\Row;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;

/** The priest's page: the healing and the shields, the shadow side and the wand, the control and the buffs, the talents the build holds and what the priest does for the party. */
final class PriestBuilder implements ClassBuilder
{
    public function handle(ClassRows $rows, ClassFacts $facts): ClassParts
    {
        $spells = Spells::of($rows->spells());
        $healing = new Healing($rows, $spells);
        $shadow = new Shadow($rows);
        $upkeep = new Upkeep($rows);
        $sheet = array_values(array_filter([
            $this->row([$healing->mirror(), $healing->where()]),
            $this->row([$healing->stack(), $healing->bySpell()]),
            $this->row([$shadow->side(), $shadow->wand()]),
            $this->row([$healing->shields(), $upkeep->control(), $upkeep->buffs()]),
        ]));
        $top = $healing->top();
        $icon = $top === null ? null : Casts::icon($top);
        $done = new Figure('Healing done', $healing->done(), Unit::Short, icon: $icon);
        $shields = new Figure('Shields cast', $spells->shield->casts, icon: Casts::icon($spells->shield));
        $raised = new Figure('Players raised', $spells->resurrection->casts, icon: Casts::icon($spells->resurrection));
        $form = new Figure('Hours in Shadowform', $shadow->formHours(), Unit::Hours, 'of '.Unit::Hours->say($facts->hours), $shadow->formIcon());

        return new ClassParts(
            $icon,
            $this->callouts($healing, $spells),
            [$done, $shields, $raised, new Figure('Fortitudes cast', $spells->fortitudes(), icon: Casts::icon($spells->fortitude->casts >= $spells->prayer->casts ? $spells->fortitude : $spells->prayer)), $form, new Figure('Hours of wand', $shadow->wandHours(), Unit::Hours, icon: $shadow->wandIcon())],
            [$done, $shields, $raised, $form, new Figure('Heal cast most', $top->name ?? 'None', icon: $icon)],
            $sheet,
            (new Talents($rows))->of($facts),
            (new Party($rows, $spells))->rows(),
            (new Standings($rows, $spells, $healing, $shadow))->all(),
        );
    }

    /** @param  list<Module|null>  $modules */
    private function row(array $modules): ?Row
    {
        $present = array_values(array_filter($modules));

        return $present === [] ? null : new Row($present);
    }

    /** @return list<Callout> */
    private function callouts(Healing $healing, Spells $spells): array
    {
        $callouts = [];
        $top = $healing->top();
        if ($top !== null) {
            $callouts[] = new Callout('podium', $top->name, 'The heal cast most', Unit::Count->say($healing->topShare()).'% of heals cast');
        }
        if ($spells->shield->casts > 0) {
            $absorbed = $healing->absorbed();
            $callouts[] = new Callout('candelabra', 'Power Word: Shield', Unit::Count->say($spells->shield->casts).' shields', $absorbed > 0 ? Unit::Count->say($absorbed).' damage absorbed' : null);
        }
        if ($spells->resurrection->casts > 0) {
            $callouts[] = new Callout('altar', 'Resurrection', Unit::Count->say($spells->resurrection->casts).' players raised');
        }

        return $callouts;
    }
}
