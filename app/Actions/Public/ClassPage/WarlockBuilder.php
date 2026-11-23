<?php

namespace App\Actions\Public\ClassPage;

use App\Contracts\ClassBuilder;
use App\Support\ClassPage\ClassFacts;
use App\Support\ClassPage\ClassParts;
use App\Support\ClassPage\Figure;
use App\Support\ClassPage\Unit;
use App\Support\ClassPage\Warlock\Casting;
use App\Support\ClassPage\Warlock\Demons;
use App\Support\ClassPage\Warlock\Party;
use App\Support\ClassPage\Warlock\Shards;
use App\Support\ClassPage\Warlock\Spells;
use App\Support\ClassPage\Warlock\Standings;
use App\Support\ClassPage\Warlock\Talents;
use App\Support\ClassPage\Warlock\Taps;
use App\Support\ClassRows\ClassRows;
use App\Support\StateKind;

/** The warlock's page: shards, Life Tap, demons, curses and banes, the talents the build holds and what the warlock does for the party. */
final class WarlockBuilder implements ClassBuilder
{
    /** The state kinds the page reads: the demon out, fights, and the auras its modules name. */
    private const array STATES = [StateKind::Pet, StateKind::Fight, StateKind::OwnBuff, StateKind::OthersBuff];

    /** The items the page reads, by the client's names. */
    private const array ITEMS = ['Soul Shard', '*Healthstone', '*Soulstone'];

    public function handle(ClassRows $rows, ClassFacts $facts): ClassParts
    {
        $rows->states(...self::STATES);
        $rows->items(...self::ITEMS);
        $spells = Spells::of($rows->spells());
        $shards = Shards::of($rows, $spells);
        $demons = Demons::of($rows, $spells);
        $taps = Taps::of($rows, $spells);
        $party = new Party($rows, $spells);
        $top = $demons->top();
        $sheet = array_values(array_filter([$shards->row(), $taps->row($facts), $demons->row(), (new Casting($rows))->row()]));

        $made = $shards->shared ? new Figure('Soul shards made', $shards->made(), icon: $shards->icon) : null;
        $lifeTaps = new Figure('Life Taps', $spells->lifeTap->casts, icon: Spells::icon($spells->lifeTap));
        $healthstones = new Figure('Healthstones made', $spells->healthstones->casts, icon: Spells::icon($spells->healthstones));
        $summoned = new Figure('Players summoned', $spells->ritual->casts, icon: Spells::icon($spells->ritual));
        $demon = $demons->shared() ? $top ?? ($demons->withMs() > 0 ? 'Another demon' : 'None') : null;

        return new ClassParts(
            Spells::icon($spells->lifeTap),
            $demons->callouts(),
            array_values(array_filter([
                $made,
                $lifeTaps,
                $demons->shared() ? new Figure('Hours with a demon', $demons->withHours(), Unit::Hours, 'of '.Unit::Hours->say($demons->playedHours()), $demons->icon($top)) : null,
                $healthstones,
                new Figure('Soulstones made', $spells->soulstones->casts, icon: Spells::icon($spells->soulstones)),
                $summoned,
            ])),
            array_values(array_filter([
                $made,
                $lifeTaps,
                $demon === null ? null : new Figure('Demon out most', $demon, icon: $demons->icon($top)),
                $healthstones,
                $summoned,
            ])),
            $sheet,
            (new Talents($rows, $spells, $shards))->of($facts),
            $party->rows(),
            (new Standings($rows, $spells, $shards, $demons, $party))->all(),
        );
    }
}
