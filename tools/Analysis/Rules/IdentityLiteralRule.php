<?php

namespace Scarlet\Analysis\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Scarlet\Analysis\HouseRule;
use Scarlet\Analysis\RatchetMode;

/** @implements Rule<Expr> */
#[HouseRule('scarlet.identityLiteral', 'domain identities use their owner constant or enum.', 'replace the identity literal with its owner constant or enum case.', RatchetMode::Zero)]
final class IdentityLiteralRule implements Rule
{
    private const COLUMNS = ['status', 'state', 'priority', 'kind', 'type', 'role', 'mode', 'visibility', 'provider', 'source', 'loot_rule'];

    public function getNodeType(): string
    {
        return Expr::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (str_contains(str_replace('\\', '/', $scope->getFile()), '/database/migrations/')) {
            return [];
        }
        if (! $this->violates($node)) {
            return [];
        }

        return [RuleErrorBuilder::message('House rule: domain identities use their owner constant or enum. Fix: replace the identity literal with its owner constant or enum case.')->identifier('scarlet.identityLiteral')->build()];
    }

    private function violates(Expr $node): bool
    {
        if ($node instanceof Expr\Assign) {
            return $this->identity($node->var) && $node->expr instanceof String_;
        }
        if ($node instanceof Expr\BinaryOp\Identical || $node instanceof Expr\BinaryOp\NotIdentical || $node instanceof Expr\BinaryOp\Equal || $node instanceof Expr\BinaryOp\NotEqual) {
            return ($this->identity($node->left) && $node->right instanceof String_) || ($this->identity($node->right) && $node->left instanceof String_);
        }
        if ($node instanceof Expr\Match_ && $this->identity($node->cond)) {
            foreach ($node->arms as $arm) {
                foreach ($arm->conds ?? [] as $condition) {
                    if ($condition instanceof String_) {
                        return true;
                    }
                }
            }
        }
        if (! $node instanceof Expr\MethodCall && ! $node instanceof Expr\StaticCall) {
            return false;
        }
        if (! $node->name instanceof Identifier || $node->isFirstClassCallable()) {
            return false;
        }
        $name = strtolower($node->name->toString());
        $args = $node->getArgs();
        if (in_array($name, ['where', 'orwhere', 'wherein', 'wherenotin', 'wherenot'], strict: true)) {
            $column = $args[0]->value ?? null;
            if ($column instanceof String_ && $this->column($column->value)) {
                $value = $args[count($args) === 3 ? 2 : 1]->value ?? null;

                return $value instanceof String_ || ($value instanceof Expr\Array_ && $this->literalIn($value));
            }
        }
        if (in_array($name, ['update', 'create', 'fill', 'forcefill', 'firstorcreate', 'updateorcreate', 'where', 'orwhere'], strict: true)) {
            foreach ($args as $arg) {
                if (! $arg->value instanceof Expr\Array_) {
                    continue;
                }
                foreach ($arg->value->items as $item) {
                    if ($item?->key instanceof String_ && $this->column($item->key->value) && $item->value instanceof String_) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function identity(Expr $node): bool
    {
        return ($node instanceof Expr\PropertyFetch || $node instanceof Expr\NullsafePropertyFetch) && $node->name instanceof Identifier && $this->column($node->name->toString());
    }

    private function column(string $name): bool
    {
        return in_array(preg_replace('/^.*\./', '', $name), self::COLUMNS, strict: true);
    }

    private function literalIn(Expr\Array_ $array): bool
    {
        foreach ($array->items as $item) {
            if ($item?->value instanceof String_) {
                return true;
            }
        }

        return false;
    }
}
