<?php

namespace App\Console\Commands;

use App\Actions\Ingest\ReconcileAddonClaims;
use App\Models\Character;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('addons:reconcile {character? : Name-Surname; every character that holds claims when left out}')]
#[Description('Decides again what the addon claims of a character count and prints how many verdicts changed')]
class AddonsReconcileCommand extends Command
{
    public function handle(ReconcileAddonClaims $reconcile): int
    {
        $name = $this->argument('character');
        $characters = Character::query()->whereIn('id', DB::table('addon_claims')->select('character_id'))->orderBy('id');
        if (is_string($name)) {
            $characters->where('name_key', Character::keyFor($name));
        }

        $found = 0;
        foreach ($characters->get(['id', 'name']) as $character) {
            $found++;
            $this->line("{$character->name}: {$reconcile->handle($character->id)} claims changed");
        }
        if ($found === 0) {
            $this->error(is_string($name) ? "No character {$name} holds claims." : 'No character holds claims.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
