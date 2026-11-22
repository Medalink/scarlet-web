<?php

namespace App\Console\Commands;

use App\Actions\Rulesets\MergeRulesets;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('rulesets:merge {--undo : put every session back on the ruleset row it had}')]
#[Description('Files every session and fact row under the ruleset of its character\'s kind, refreshes the rollups and checks the totals did not move')]
class MergeRulesetsCommand extends Command
{
    public function handle(MergeRulesets $merge): int
    {
        $report = $merge->handle((bool) $this->option('undo'));

        $this->table(['table', 'rows before', 'rows after'], array_map(fn (array $row): array => [$row['table'], $row['before'], $row['after']], $report['tables']));
        $this->table(['ruleset', 'sessions before', 'sessions after'], array_map(fn (array $row): array => [$row['key'], $row['before'], $row['after']], $report['rulesets']));
        foreach ($report['notes'] as $note) {
            $this->line($note);
        }
        $failed = 0;
        foreach ($report['checks'] as $check) {
            $check['ok'] ? $this->info('ok    '.$check['name'].': '.$check['detail']) : $this->error('FAIL  '.$check['name'].': '.$check['detail']);
            $failed += $check['ok'] ? 0 : 1;
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
