<?php

namespace App\Console\Commands;

use App\Actions\Rulesets\RefileSessions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('rulesets:refile')]
#[Description('Files every session under the ruleset of its character, decoding the ones that sit under another')]
class RefileRulesetsCommand extends Command
{
    public function handle(RefileSessions $refile): int
    {
        $this->info($refile->handle().' sessions queued.');

        return self::SUCCESS;
    }
}
