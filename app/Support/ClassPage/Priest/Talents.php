<?php

namespace App\Support\ClassPage\Priest;

use App\Support\ClassPage\ClassFacts;
use App\Support\ClassPage\Icon;
use App\Support\ClassPage\Procs;
use App\Support\ClassPage\TalentModule;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\SpellTotals;

/** The modules of the priest talents the latest build holds: Inner Focus, Spirit of Redemption, Lightwell, Power Infusion, Shadowform and Vampiric Embrace. */
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
                'Inner Focus', 'Shadowform' => $this->procs->buffed($this->spell($held->name), $held->name),
                'Spirit of Redemption' => $this->procs->held($held->name, 'Heals cast as one'),
                'Lightwell', 'Power Infusion', 'Vampiric Embrace' => $this->procs->cast($this->spell($held->name)),
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
