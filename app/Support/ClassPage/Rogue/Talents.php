<?php

namespace App\Support\ClassPage\Rogue;

use App\Support\ClassPage\ClassFacts;
use App\Support\ClassPage\Icon;
use App\Support\ClassPage\Procs;
use App\Support\ClassPage\TalentModule;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\SpellTotals;

/** The modules of the rogue talents the latest build holds: Blade Flurry, Adrenaline Rush, Riposte, Cold Blood, Remorseless Attacks, Ghostly Strike and Premeditation. */
final readonly class Talents
{
    private Procs $procs;

    public function __construct(private ClassRows $rows)
    {
        $this->procs = new Procs($rows);
    }

    /** @return list<TalentModule> one for each held talent that has a module, in the build's order */
    public function of(ClassFacts $facts): array
    {
        $modules = [];
        foreach ($facts->talents as $held) {
            $blocks = match ($held->name) {
                'Blade Flurry', 'Adrenaline Rush', 'Cold Blood' => $this->procs->buffed($this->spell($held->name), $held->name),
                'Remorseless Attacks' => $this->procs->proc('Remorseless', 'an attack'),
                'Riposte', 'Ghostly Strike' => $this->procs->cast($this->spell($held->name)),
                'Premeditation' => $this->procs->castUnder($this->spell($held->name), Spells::STEALTH, 'from stealth'),
                default => null,
            };
            if ($blocks === null) {
                continue;
            }
            $modules[] = new TalentModule($held->name, $held->points, $held->max, $blocks, $held->spellId === 0 ? null : Icon::spell($held->spellId));
        }

        return $modules;
    }

    private function spell(string $name): SpellTotals
    {
        return $this->rows->spells()->sum($name, $name);
    }
}
