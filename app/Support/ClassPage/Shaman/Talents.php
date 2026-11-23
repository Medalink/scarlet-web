<?php

namespace App\Support\ClassPage\Shaman;

use App\Support\ClassPage\ClassFacts;
use App\Support\ClassPage\Icon;
use App\Support\ClassPage\Procs;
use App\Support\ClassPage\TalentModule;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\SpellTotals;

/** The modules of the shaman talents the latest build holds: Stormstrike, Elemental Focus, Flurry, Lightning Mastery, Nature's Swiftness and Mana Tide Totem. */
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
                'Stormstrike', 'Mana Tide Totem' => $this->procs->cast($this->spell($held->name)),
                'Elemental Focus' => $this->procs->proc('Clearcasting'),
                'Flurry' => $this->procs->held($held->name, 'Casts under it'),
                'Lightning Mastery', "Nature's Swiftness" => $this->procs->buffed($this->spell($held->name), $held->name),
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
