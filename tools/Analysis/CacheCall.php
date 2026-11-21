<?php

namespace Scarlet\Analysis;

use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;

final class CacheCall
{
    public static function containsLiteral(?Expr $key): bool
    {
        if ($key instanceof String_ || $key instanceof InterpolatedString) {
            return true;
        }

        return $key instanceof Expr\BinaryOp\Concat && (self::containsLiteral($key->left) || self::containsLiteral($key->right));
    }

    public static function key(Expr $node, Scope $scope): ?Expr
    {
        if (! $node instanceof Expr\StaticCall && ! $node instanceof Expr\MethodCall) {
            return null;
        }
        if (! $node->name instanceof Identifier || $node->isFirstClassCallable() || ! in_array(strtolower($node->name->toString()), ['remember', 'put', 'get', 'forget', 'flexible', 'add', 'has', 'pull', 'lock'], strict: true)) {
            return null;
        }
        $receiver = $node instanceof Expr\StaticCall ? $node->class : $node->var;
        if ($receiver instanceof Name && $scope->resolveName($receiver) === 'Illuminate\\Support\\Facades\\Cache') {
            return $node->getArgs()[0]->value ?? null;
        }
        if ($receiver instanceof Expr\FuncCall && $receiver->name instanceof Name && $receiver->name->toString() === 'cache') {
            return $node->getArgs()[0]->value ?? null;
        }

        return null;
    }
}
