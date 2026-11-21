<?php

namespace App\Console\Commands;

use App\Actions\Rulesets\DetectDueRulesets;
use App\Jobs\DetectRulesets;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('rulesets:detect {--now : look up here, for as long as it takes, instead of queueing the job}')]
#[Description('Asks the Forever Armory for the ruleset of every character that is due')]
class DetectRulesetsCommand extends Command
{
    public function handle(DetectDueRulesets $detect): int
    {
        if (! $this->option('now')) {
            DetectRulesets::dispatch();

            return self::SUCCESS;
        }

        $this->info(collect($detect->handle(3000))->map(fn (int $n, string $outcome): string => "{$n} {$outcome}")->implode(', ').'.');

        return self::SUCCESS;
    }
}
