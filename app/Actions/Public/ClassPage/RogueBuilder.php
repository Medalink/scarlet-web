<?php

namespace App\Actions\Public\ClassPage;

use App\Contracts\ClassBuilder;
use App\Support\ClassPage\Callout;
use App\Support\ClassPage\Casts;
use App\Support\ClassPage\ClassFacts;
use App\Support\ClassPage\ClassParts;
use App\Support\ClassPage\Figure;
use App\Support\ClassPage\Icon;
use App\Support\ClassPage\Module;
use App\Support\ClassPage\Rogue\Fighting;
use App\Support\ClassPage\Rogue\Finishers;
use App\Support\ClassPage\Rogue\Hands;
use App\Support\ClassPage\Rogue\Openers;
use App\Support\ClassPage\Rogue\Party;
use App\Support\ClassPage\Rogue\Poisons;
use App\Support\ClassPage\Rogue\Spells;
use App\Support\ClassPage\Rogue\Standings;
use App\Support\ClassPage\Rogue\Stealth;
use App\Support\ClassPage\Rogue\Talents;
use App\Support\ClassPage\Row;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\StateKind;

/** The rogue's page: how a fight opened and ended, stealth, pockets and locks, poisons, the talents the build holds and what the rogue does for the party. */
final class RogueBuilder implements ClassBuilder
{
    /** The state kinds the page reads. */
    private const array STATES = [StateKind::Fight, StateKind::OthersBuff, StateKind::OwnBuff];

    private const int CHEAP_SHOT = 1833;

    public function handle(ClassRows $rows, ClassFacts $facts): ClassParts
    {
        $rows->states(...self::STATES);
        $spells = Spells::of($rows->spells());
        $stealth = Stealth::of($rows);
        $openers = Openers::of($rows);
        $poisons = Poisons::of($rows);
        $hands = new Hands($rows, $spells);
        $fighting = new Fighting($rows, $spells);
        $finishers = new Finishers($rows, $spells);
        $sheet = array_values(array_filter([
            $this->row([$openers->module(), $stealth->module()]),
            $this->row([$finishers->module(), $hands->pockets()]),
            $this->row([$fighting->attacks(), $hands->locks()]),
            $this->row([$poisons->module(), $fighting->escapes(), $fighting->kick()]),
        ]));
        $hours = new Figure('Hours in stealth', $stealth->hours(), Unit::Hours, 'of '.Unit::Hours->say($facts->hours), $stealth->icon);
        $opened = new Figure('Openers from stealth', $stealth->opened, icon: Icon::spell(self::CHEAP_SHOT));
        $pockets = new Figure('Pockets picked', $spells->pickPocket->casts, icon: Casts::icon($spells->pickPocket));
        $locks = new Figure('Locks picked', $spells->pickLock->casts, icon: Casts::icon($spells->pickLock));
        $vanishes = new Figure('Vanishes', $spells->vanish->casts, icon: Casts::icon($spells->vanish));

        return new ClassParts(
            $stealth->icon,
            $this->callouts($poisons, $spells),
            [$hours, $opened, $pockets, $locks, new Figure('Poisons applied', $poisons->applied(), icon: $poisons->top()['icon'] ?? null), $vanishes],
            [$hours, $opened, $pockets, $locks, $vanishes],
            $sheet,
            (new Talents($rows))->of($facts),
            (new Party($rows, $spells, $openers))->rows(),
            (new Standings($rows, $spells, $stealth, $fighting))->all(),
        );
    }

    /** @param  list<Module|null>  $modules */
    private function row(array $modules): ?Row
    {
        $present = array_values(array_filter($modules));

        return $present === [] ? null : new Row($present);
    }

    /** @return list<Callout> */
    private function callouts(Poisons $poisons, Spells $spells): array
    {
        $callouts = [];
        $top = $poisons->top();
        if ($top !== null) {
            $callouts[] = new Callout('vials', $top['name'], 'The poison applied most', Unit::Count->say($top['applied'] / $poisons->applied() * 100).'% of applications');
        }
        if ($spells->pickLock->casts > 0) {
            $callouts[] = new Callout('chest', 'Locks picked', Unit::Count->say($spells->pickLock->casts).' boxes, doors and chests');
        }
        if ($spells->pickPocket->casts > 0) {
            $callouts[] = new Callout('sack', 'Pick Pocket', Unit::Count->say($spells->pickPocket->casts).' pockets picked');
        }

        return $callouts;
    }
}
