<?php

namespace App\Actions\Public\ClassPage;

use App\Support\ClassPage\ClassRead;
use App\Support\ClassPage\Comparison;
use App\Support\ClassPage\Standing;
use App\Support\ClassPage\StandingFigure;
use App\View\Format;

class ReadClassComparison
{
    private const int TOP_LEVEL = 60;

    private const int MOST_PERCENT = 99;

    /**
     * The figures of a character's page as places among the public characters of its class in its level bracket, from
     * the stored standings of the others. Null until the bracket holds `ClassPeers::MIN_CHARACTERS` characters.
     */
    public function handle(ClassRead $read): ?Comparison
    {
        $peers = $read->facts->peers;
        $bracket = $peers->bracket();
        if ($bracket === null) {
            return null;
        }
        $standings = [];
        $characters = 0;
        foreach ($read->parts->standings as $figure) {
            $place = $peers->place($figure->key, $figure->value);
            if ($place === null) {
                continue;
            }
            $characters = max($characters, $place['characters']);
            $standings[] = $this->standing($figure, $place);
        }
        if ($standings === []) {
            return null;
        }
        [$from, $to] = $bracket->range();

        return new Comparison(strtolower(Format::className($read->class->value)).'s', $from, min($to, self::TOP_LEVEL), $characters, $standings);
    }

    /** @param  array{characters: int, below: int}  $place */
    private function standing(StandingFigure $figure, array $place): Standing
    {
        $others = max(1, $place['characters'] - 1);

        $percent = min(self::MOST_PERCENT, (int) floor($place['below'] / $others * 100));

        return new Standing($figure->label, $figure->value, $percent, $figure->unit, $figure->icon);
    }
}
