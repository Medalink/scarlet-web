<?php

use Scarlet\Analysis\Guidance\RuleTable;

$root = dirname(__DIR__, levels: 2);
require $root.'/vendor/autoload.php';

RuleTable::write($root.'/AGENTS.md', RuleTable::collect(), RuleTable::facts($root));
echo 'Rendered '.count(RuleTable::collect())." analyzer house rules.\n";
