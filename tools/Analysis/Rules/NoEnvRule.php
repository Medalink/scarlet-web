<?php

namespace Scarlet\Analysis\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Scarlet\Analysis\HouseRule;
use Scarlet\Analysis\RatchetMode;

/** @implements Rule<Node\Expr\FuncCall> */
#[HouseRule('scarlet.env', 'app code reads configuration.', 'move env() to config/ and use config().', RatchetMode::Zero)]
final class NoEnvRule implements Rule
{
    public function getNodeType(): string
    {
        return Node\Expr\FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Node\Name || strtolower($node->name->toString()) !== 'env') {
            return [];
        }
        $app = str_replace('\\', '/', dirname(__DIR__, levels: 3)).'/app/';
        if (! str_starts_with(str_replace('\\', '/', $scope->getFile()), $app) && ! str_starts_with($scope->getNamespace() ?? '', 'App\\')) {
            return [];
        }

        return [RuleErrorBuilder::message('House rule: app code reads configuration. Fix: move env() to config/ and use config().')->identifier('scarlet.env')->build()];
    }
}
