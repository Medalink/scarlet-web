<?php

namespace App\Actions\Public\ClassPage;

use App\Contracts\ClassBuilder;
use App\Support\ClassPage\Callout;
use App\Support\ClassPage\ClassFacts;
use App\Support\ClassPage\ClassParts;
use App\Support\ClassPage\Figure;
use App\Support\ClassPage\Mage\Casting;
use App\Support\ClassPage\Mage\Conjuring;
use App\Support\ClassPage\Mage\Control;
use App\Support\ClassPage\Mage\Party;
use App\Support\ClassPage\Mage\Portals;
use App\Support\ClassPage\Mage\Schools;
use App\Support\ClassPage\Mage\Spells;
use App\Support\ClassPage\Mage\Standings;
use App\Support\ClassPage\Mage\Talents;
use App\Support\ClassPage\Module;
use App\Support\ClassPage\Row;
use App\Support\ClassPage\Span;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\StateKind;

/** The mage's page: conjured water and food, damage by school, portals, Polymorph, escapes, the talents the build holds and what the mage does for the party. */
final class MageBuilder implements ClassBuilder
{
    /** The state kinds the page reads. */
    private const array STATES = [StateKind::Fight, StateKind::OwnBuff];

    public function handle(ClassRows $rows, ClassFacts $facts): ClassParts
    {
        $rows->states(...self::STATES);
        $spells = Spells::of($rows->spells());
        $conjuring = Conjuring::of($rows, $spells);
        $portals = Portals::of($rows->spells(), $spells);
        $schools = Schools::of($rows)->module();
        $casting = new Casting($rows, $spells);
        $water = Spells::icon($spells->conjureWater);

        return new ClassParts(
            $water ?? Spells::icon($spells->portals) ?? Spells::icon($spells->polymorph),
            $this->callouts($spells, $conjuring, $portals),
            array_values(array_filter([
                $conjuring->shared ? new Figure('Water conjured', $conjuring->waterMade(), icon: $water) : null,
                new Figure('Portals opened', $spells->portals->casts, icon: Spells::icon($spells->portals)),
                new Figure('Teleports', $spells->teleports->casts, icon: Spells::icon($spells->teleports)),
                new Figure('Sheep made', $spells->sheep->casts, icon: Spells::icon($spells->sheep)),
                new Figure('Blinks', $spells->blink->casts, icon: Spells::icon($spells->blink)),
                new Figure('Frost Novas', $spells->frostNova->casts, icon: Spells::icon($spells->frostNova)),
            ])),
            array_values(array_filter([
                $conjuring->shared ? new Figure('Water conjured', $conjuring->waterMade(), icon: $water) : null,
                new Figure('Portals opened', $spells->portals->casts, icon: Spells::icon($spells->portals)),
                new Figure('Sheep made', $spells->sheep->casts, icon: Spells::icon($spells->sheep)),
                new Figure('Kills in one fight, most', $casting->mostKills(), icon: Spells::icon($spells->blizzard)),
                new Figure('Blinks', $spells->blink->casts, icon: Spells::icon($spells->blink)),
            ])),
            array_values(array_filter([
                $conjuring->row(),
                $this->travel($schools, $portals),
                $casting->row(),
                (new Control($rows, $spells, $conjuring))->row($facts),
            ])),
            (new Talents($rows, $spells))->of($facts),
            (new Party($rows, $spells, $conjuring))->rows(),
            (new Standings($rows, $spells, $conjuring, $casting))->all(),
        );
    }

    /** @return list<Callout> */
    private function callouts(Spells $spells, Conjuring $conjuring, Portals $portals): array
    {
        $callouts = [];
        $most = $portals->mostOpened();
        if ($most !== null) {
            $callouts[] = new Callout('portal', 'Portals', Unit::Count->say($spells->portals->casts).' opened', $most['city'].' most, '.Unit::Count->say($most['share']).'%');
        }
        if ($spells->sheep->casts > 0) {
            $callouts[] = new Callout('sheep', 'Polymorph', Unit::Count->say($spells->sheep->casts).' sheep', Unit::Count->say($spells->sheep->fightCasts).' cast in a fight');
        }
        if ($conjuring->waterMade() > 0) {
            $handed = $conjuring->handedWater();
            $callouts[] = new Callout('bottles', 'Conjured water', Unit::Count->say($conjuring->waterMade()).' conjured', $handed === 0 ? null : Unit::Count->say($handed).' handed to others');
        }

        return $callouts;
    }

    private function travel(?Module $schools, Portals $portals): ?Row
    {
        $modules = array_values(array_filter([$schools, $portals->module($schools === null ? Span::W12 : Span::W5)]));

        return $modules === [] ? null : new Row($modules);
    }
}
