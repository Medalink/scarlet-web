<?php

namespace App\Actions\Public\ClassPage;

use App\Contracts\ClassBuilder;
use App\Support\ClassPage\ClassFacts;
use App\Support\ClassPage\ClassParts;
use App\Support\ClassPage\Druid\Casting;
use App\Support\ClassPage\Druid\Forms;
use App\Support\ClassPage\Druid\Party;
use App\Support\ClassPage\Druid\Spells;
use App\Support\ClassPage\Druid\Standings;
use App\Support\ClassPage\Druid\Talents;
use App\Support\ClassPage\Figure;
use App\Support\ClassPage\Row;
use App\Support\ClassPage\Span;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\StateKind;

/** The druid's page: the form worn by level, the shifts between forms, what each form did, control, the talents the build holds and Rebirth and Innervate. */
final class DruidBuilder implements ClassBuilder
{
    /** The state kinds the page reads. */
    private const array STATES = [StateKind::Fight, StateKind::Mount, StateKind::OthersBuff, StateKind::OwnBuff];

    public function handle(ClassRows $rows, ClassFacts $facts): ClassParts
    {
        $rows->states(...self::STATES);
        $spells = Spells::of($rows->spells());
        $forms = Forms::of($rows);
        $casting = new Casting($rows, $spells);
        $top = $forms->top();
        $shifts = new Figure('Shapeshifts', $forms->shifts, icon: Forms::icon('moonkin'));
        $innervates = new Figure('Innervates', $spells->innervate->casts, icon: Spells::icon($spells->innervate));
        $rebirths = new Figure('Rebirths', $spells->rebirth->casts, icon: Spells::icon($spells->rebirth));
        $travel = new Figure('Hours in Travel Form', $forms->hours('travel'), Unit::Hours, icon: Forms::icon('travel'));

        return new ClassParts(
            Forms::icon('bear'),
            $forms->callouts(),
            [
                $shifts,
                new Figure('Hours as a bear', $forms->hours('bear'), Unit::Hours, icon: Forms::icon('bear')),
                new Figure('Hours as a cat', $forms->hours('cat'), Unit::Hours, icon: Forms::icon('cat')),
                $travel,
                $innervates,
                $rebirths,
            ],
            [
                new Figure('Form worn most', $top === null ? '0' : Forms::label($top), icon: $top === null ? null : Forms::icon($top)),
                $shifts,
                $travel,
                $innervates,
                $rebirths,
            ],
            array_values(array_filter([
                $forms->row(),
                $this->travel($forms),
                $casting->didRow(),
                $casting->sheetRow(),
            ])),
            (new Talents($rows, $spells, $forms))->of($facts),
            (new Party($rows, $spells, $forms))->rows(),
            (new Standings($rows, $spells, $forms))->all(),
        );
    }

    private function travel(Forms $forms): ?Row
    {
        $travelling = $forms->travel(Span::W5);
        $link = $forms->link($travelling === null ? Span::W12 : Span::W7);
        if ($link === null && $travelling !== null) {
            $travelling = $forms->travel(Span::W12);
        }
        $modules = array_values(array_filter([$link, $travelling]));

        return $modules === [] ? null : new Row($modules);
    }
}
