<?php

namespace App\Actions\Public\ClassPage;

use App\Contracts\ClassBuilder;
use App\Support\ClassPage\Casts;
use App\Support\ClassPage\ClassFacts;
use App\Support\ClassPage\ClassParts;
use App\Support\ClassPage\Figure;
use App\Support\ClassPage\Icon;
use App\Support\ClassPage\Module;
use App\Support\ClassPage\Row;
use App\Support\ClassPage\Shaman\Fighting;
use App\Support\ClassPage\Shaman\Party;
use App\Support\ClassPage\Shaman\Spells;
use App\Support\ClassPage\Shaman\Standings;
use App\Support\ClassPage\Shaman\Talents;
use App\Support\ClassPage\Shaman\Totems;
use App\Support\ClassPage\Shaman\Walks;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\StateKind;

/** The shaman's page: the totems and how they ended, the shocks and imbues, coming back and getting around, the talents the build holds and what the shaman does for the party. */
final class ShamanBuilder implements ClassBuilder
{
    /** The state kinds the page reads. */
    private const array STATES = [StateKind::Fight, StateKind::Mount, StateKind::OthersBuff, StateKind::OwnBuff, StateKind::Totem];

    public function handle(ClassRows $rows, ClassFacts $facts): ClassParts
    {
        $rows->states(...self::STATES);
        $spells = Spells::of($rows->spells());
        $totems = Totems::of($rows);
        $fighting = new Fighting($rows, $spells);
        $walks = new Walks($rows, $spells);
        $sheet = array_values(array_filter([
            $this->row([$totems->columns()]),
            $this->row([$totems->ended(), $totems->inFight()]),
            $this->row([$totems->byLevel(), $fighting->shocksModule()]),
            $this->row([$fighting->imbues(), $walks->back(), $walks->travel()]),
        ]));
        $top = $totems->top();
        $icon = $top === null ? null : Icon::spell($top['ref']);
        $dropped = new Figure('Totems dropped', $totems->drops(), icon: $icon);
        $shocks = new Figure('Shocks cast', $fighting->shocks(), icon: Casts::icon($spells->earthShock));
        $wolf = new Figure('Hours as a Ghost Wolf', $walks->wolfHours(), Unit::Hours, 'of '.Unit::Hours->say($walks->playedHours()), $walks->wolfIcon());
        $reincarnations = new Figure('Reincarnations', $spells->reincarnation->casts, icon: Casts::icon($spells->reincarnation));

        return new ClassParts(
            $icon,
            $totems->callouts(),
            [$dropped, new Figure('Hours a totem stood', $totems->stoodHours(), Unit::Hours, icon: Icon::spell(Spells::MANA_TIDE)), $shocks, $wolf, $reincarnations, new Figure('Astral Recalls', $spells->recall->casts, icon: Casts::icon($spells->recall))],
            [$dropped, new Figure('Totem dropped most', $top === null ? 'None' : Totems::short($top['name']), icon: $icon), $shocks, $wolf, $reincarnations],
            $sheet,
            (new Talents($rows))->of($facts),
            (new Party($rows, $totems))->rows(),
            (new Standings($rows, $spells, $totems, $fighting, $walks))->all(),
        );
    }

    /** @param  list<Module|null>  $modules */
    private function row(array $modules): ?Row
    {
        $present = array_values(array_filter($modules));

        return $present === [] ? null : new Row($present);
    }
}
