<?php

namespace Scarlet\Analysis\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Scarlet\Analysis\HouseRule;
use Scarlet\Analysis\RatchetMode;

/** @implements Rule<Stmt> */
#[HouseRule('scarlet.noElse', 'no else or elseif.', 'return, throw, continue or break on the invalid case first.', RatchetMode::Zero)]
final class NoElseRule implements Rule
{
    public function getNodeType(): string
    {
        return Stmt::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node instanceof Stmt\Else_ && ! $node instanceof Stmt\ElseIf_) {
            return [];
        }

        return [RuleErrorBuilder::message('House rule: no else or elseif. Fix: return, throw, continue or break on the invalid case first.')->identifier('scarlet.noElse')->build()];
    }
}
