<?php

namespace App\Console\Commands;

use App\Actions\Rollups\RebuildSessionRates;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sessions:rates')]
#[Description('Rebuilds session_rates_bracket, the percentiles of XP, kills and quests an hour by ruleset and ten-level bracket that a session recap compares against')]
class RebuildSessionRatesCommand extends Command
{
    public function handle(RebuildSessionRates $rebuild): int
    {
        $this->line('wrote '.$rebuild->handle().' rows');

        return self::SUCCESS;
    }
}
