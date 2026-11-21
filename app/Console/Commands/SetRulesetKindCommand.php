<?php

namespace App\Console\Commands;

use App\Models\Ruleset;
use App\Models\RulesetKind;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('rulesets:kind {kind : normal, pvp, roleplay, roleplay-pvp or none} {ids?* : ruleset ids; none means every row with no kind yet}')]
#[Description('Records which WoW Forever ruleset rows are, as their players confirmed; "none" clears it')]
class SetRulesetKindCommand extends Command
{
    public function handle(): int
    {
        $arg = (string) $this->argument('kind');
        $kind = $arg === 'none' ? null : RulesetKind::tryFrom($arg);
        if ($arg !== 'none' && ($kind === null || ! $kind->available())) {
            $this->error("{$arg} is not a ruleset a character can be on.");

            return self::FAILURE;
        }
        /** @var list<string> $ids */
        $ids = (array) $this->argument('ids');
        $rows = $ids === [] ? Ruleset::query()->whereNull('kind') : Ruleset::query()->whereIn('id', array_map('intval', $ids));
        $n = $rows->update(['kind' => $kind?->value]);
        $this->info("{$n} rulesets set to ".($kind?->label() ?? 'none').'.');

        return self::SUCCESS;
    }
}
