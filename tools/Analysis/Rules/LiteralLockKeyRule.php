<?php

namespace Scarlet\Analysis\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Scarlet\Analysis\CacheCall;
use Scarlet\Analysis\HouseRule;
use Scarlet\Analysis\RatchetMode;

/** @implements Rule<Expr> */
#[HouseRule('scarlet.lockKey', 'lock keys have an owner.', 'obtain the lock key from its domain owner.', RatchetMode::Zero)]
final class LiteralLockKeyRule implements Rule
{
    public function getNodeType(): string
    {
        return Expr::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! CacheCall::containsLiteral(CacheCall::key($node, $scope)) || ! $node->name instanceof Identifier || strtolower($node->name->toString()) !== 'lock') {
            return [];
        }

        return [RuleErrorBuilder::message('House rule: lock keys have an owner. Fix: obtain the lock key from its domain owner.')->identifier('scarlet.lockKey')->build()];
    }
}
