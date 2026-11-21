<?php

namespace Scarlet\Analysis\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Scarlet\Analysis\HouseRule;
use Scarlet\Analysis\RatchetMode;

/** @implements Rule<Expr\StaticCall> */
#[HouseRule('scarlet.blockingInTransaction', 'transactions contain database work only.', 'move blocking calls outside DB::transaction().', RatchetMode::Zero)]
final class NoBlockingInTransactionRule implements Rule
{
    public function getNodeType(): string
    {
        return Expr\StaticCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->class instanceof Node\Name || $scope->resolveName($node->class) !== 'Illuminate\\Support\\Facades\\DB' || ! $node->name instanceof Node\Identifier || $node->name->toLowerString() !== 'transaction' || $node->isFirstClassCallable()) {
            return [];
        }
        $closure = $node->getArgs()[0]->value ?? null;
        if (! $closure instanceof Expr\Closure && ! $closure instanceof Expr\ArrowFunction) {
            return [];
        }
        $calls = (new NodeFinder)->find($closure, function (Node $child) use ($scope): bool {
            if ($child instanceof Expr\FuncCall && $child->name instanceof Node\Name) {
                return in_array(strtolower($child->name->toString()), ['sleep', 'usleep'], strict: true);
            }

            return $child instanceof Expr\StaticCall && $child->class instanceof Node\Name && in_array($scope->resolveName($child->class), ['Illuminate\\Support\\Facades\\Http', 'Illuminate\\Support\\Facades\\Process', 'Illuminate\\Support\\Sleep'], strict: true);
        });

        return array_map(fn (Node $call) => RuleErrorBuilder::message('House rule: transactions contain database work only. Fix: move blocking calls outside DB::transaction().')->identifier('scarlet.blockingInTransaction')->line($call->getStartLine())->build(), $calls);
    }
}
