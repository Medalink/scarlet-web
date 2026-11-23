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

        return new ClassParts(
            Spells::icon($spells->lifeTap),
            $demons->callouts(),
            [
                new Figure('Soul shards made', $shards->made(), icon: $shards->icon),
                new Figure('Life Taps', $spells->lifeTap->casts, icon: Spells::icon($spells->lifeTap)),
                new Figure('Hours with a demon', $demons->withHours(), Unit::Hours, 'of '.Unit::Hours->say($facts->hours), $demons->icon($top)),
                new Figure('Healthstones made', $spells->healthstones->casts, icon: Spells::icon($spells->healthstones)),
                new Figure('Soulstones made', $spells->soulstones->casts, icon: Spells::icon($spells->soulstones)),
                new Figure('Players summoned', $spells->ritual->casts, icon: Spells::icon($spells->ritual)),
            ],
            [
                new Figure('Soul shards made', $shards->made(), icon: $shards->icon),
                new Figure('Life Taps', $spells->lifeTap->casts, icon: Spells::icon($spells->lifeTap)),
                new Figure('Demon out most', $top ?? 'None', icon: $demons->icon($top)),
                new Figure('Healthstones made', $spells->healthstones->casts, icon: Spells::icon($spells->healthstones)),
                new Figure('Players summoned', $spells->ritual->casts, icon: Spells::icon($spells->ritual)),
            ],
            $sheet,
            (new Talents($rows, $spells, $shards))->of($facts),
            $party->rows(),
            (new Standings($rows, $spells, $shards, $demons, $party))->all(),
        );
    }
}
