<?php

namespace App\Console\Commands;

use App\Actions\Rulesets\DeclareCharacterKind;
use App\Actions\Rulesets\RefileSessions;
use App\Models\RulesetKind;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('rulesets:declare {kind : normal, pvp, roleplay or roleplay-pvp} {ids?* : character ids; none means every character with no answer whose sessions all sit under rows of this kind}')]
#[Description('Records the ruleset the site owner confirmed for characters recorded before the Armory or a realm could name it')]
class DeclareRulesetCommand extends Command
{
    public function handle(DeclareCharacterKind $declare, RefileSessions $refile): int
    {
        $kind = RulesetKind::tryFrom((string) $this->argument('kind'));
        if ($kind === null || ! $kind->available()) {
            $this->error($this->argument('kind').' is not a ruleset a character can be on.');

            return self::FAILURE;
        }
        /** @var list<string> $ids */
        $ids = (array) $this->argument('ids');
        $n = $declare->handle($kind, array_map('intval', $ids));
        $this->info("{$n} characters declared {$kind->label()}, {$refile->handle()} sessions queued.");

        return self::SUCCESS;
    }
}
