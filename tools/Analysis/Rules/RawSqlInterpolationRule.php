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

/** @implements Rule<Expr> */
#[HouseRule('scarlet.rawSqlInterpolation', 'SQL values use bindings.', 'replace interpolated SQL with placeholders and parameter bindings.', RatchetMode::Zero)]
final class RawSqlInterpolationRule implements Rule
{
    public function getNodeType(): string
    {
        return Expr::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if ((! $node instanceof Expr\MethodCall && ! $node instanceof Expr\StaticCall) || ! $node->name instanceof Node\Identifier || $node->isFirstClassCallable()) {
            return [];
        }
        $name = strtolower($node->name->toString());
        $raw = in_array($name, ['whereraw', 'selectraw', 'orderbyraw', 'havingraw'], strict: true);
        if ($node instanceof Expr\StaticCall && $node->class instanceof Node\Name && $scope->resolveName($node->class) === 'Illuminate\\Support\\Facades\\DB') {
            $raw = $raw || in_array($name, ['raw', 'statement', 'select'], strict: true);
        }
        $sql = $node->getArgs()[0]->value ?? null;
        if (! $raw || (! $sql instanceof Node\Scalar\InterpolatedString && ! $sql instanceof Expr\BinaryOp\Concat)) {
            return [];
        }
        if ((new NodeFinder)->findFirstInstanceOf($sql, Expr\Variable::class) === null) {
            return [];
        }

        return [RuleErrorBuilder::message('House rule: SQL values use bindings. Fix: replace interpolated SQL with placeholders and parameter bindings.')->identifier('scarlet.rawSqlInterpolation')->build()];
    }
}
