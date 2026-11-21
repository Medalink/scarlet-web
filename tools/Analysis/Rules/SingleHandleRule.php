<?php

namespace Scarlet\Analysis\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Scarlet\Analysis\HouseRule;
use Scarlet\Analysis\RatchetMode;

/** @implements Rule<Node\Stmt\Class_> */
#[HouseRule('scarlet.actionShape', 'Actions expose handle().', 'move other public operations into separate Actions.', RatchetMode::Zero)]
final class SingleHandleRule implements Rule
{
    public function getNodeType(): string
    {
        return Node\Stmt\Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $namespace = $scope->getNamespace() ?? '';
        if (! str_starts_with($namespace, 'App\\Actions')) {
            return [];
        }
        if (preg_match('/(Result|Options|Outcome|Summary|Report)$/', $node->name?->toString() ?? '')) {
            return [];
        }
        $invalid = false;
        foreach ($node->getMethods() as $method) {
            if (! $method->isPublic() || in_array(strtolower($method->name->toString()), ['__construct', 'handle'], strict: true)) {
                continue;
            }
            $statements = $method->stmts ?? [];
            $accessor = count($statements) === 1 && $statements[0] instanceof Node\Stmt\Return_ && $statements[0]->expr instanceof Node\Expr\PropertyFetch && $statements[0]->expr->var instanceof Node\Expr\Variable && $statements[0]->expr->var->name === 'this';
            if ($node->isReadonly() && $accessor) {
                continue;
            }
            $invalid = true;
        }
        if (! $invalid) {
            return [];
        }

        return [RuleErrorBuilder::message('House rule: Actions expose handle(). Fix: move other public operations into separate Actions.')->identifier('scarlet.actionShape')->build()];
    }
}
