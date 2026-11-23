<?php

namespace App\Console\Commands;

use App\Actions\Rollups\RebuildCharacterRates;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('characters:rates')]
#[Description('Rebuilds character_rates_bracket, the percentiles of kills, XP, deaths, quests, miles and jumps an hour by ruleset, class and ten-level bracket that a character page compares against')]
class RebuildCharacterRatesCommand extends Command
{
    public function handle(RebuildCharacterRates $rebuild): int
    {
        $this->line('wrote '.$rebuild->handle().' rows');

        return self::SUCCESS;
    }
}
