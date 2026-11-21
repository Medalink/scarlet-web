<?php

use Scarlet\Analysis\Guidance\GuidanceFactsCheck;
use Scarlet\Analysis\Guidance\RuleTable;

/**
 * `composer analyse`: the house-rules block in AGENTS.md is current, no prose contradicts the
 * PHPStan level, then PHPStan runs Larastan with the house rules. Every rule is Zero: one
 * violation fails, there is no baseline.
 */
$root = dirname(__DIR__, levels: 2);
if (! is_file($root.'/vendor/autoload.php')) {
    fwrite(STDERR, "vendor/ missing; run composer install\n");
    exit(1);
}
require $root.'/vendor/autoload.php';
chdir($root);

$content = (string) file_get_contents($root.'/AGENTS.md');
$rendered = RuleTable::render(RuleTable::collect(), RuleTable::facts($root));
if (preg_match('/<!-- house-rules:start -->.*?<!-- house-rules:end -->/s', $content, $matches) !== 1 || $matches[0] !== $rendered) {
    fwrite(STDERR, "guidance.stale: AGENTS.md differs from the rendered house rules. Fix: run `composer guidance` and commit.\n");
    exit(1);
}

$facts = GuidanceFactsCheck::check([$root.'/AGENTS.md', $root.'/DEV.md', $root.'/README.md'], RuleTable::phpstanLevel($root));
if ($facts !== []) {
    fwrite(STDERR, "guidance.facts: a sentence contradicts the code.\n".implode("\n", $facts)."\n");
    exit(1);
}

passthru(escapeshellarg(PHP_BINARY).' -d opcache.enable_cli=0 -d memory_limit=2G vendor/bin/phpstan analyse --no-progress --ansi', $status);
exit($status);
