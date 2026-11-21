<?php

namespace Scarlet\Analysis\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Scarlet\Analysis\CacheCall;
use Scarlet\Analysis\HouseRule;
use Scarlet\Analysis\RatchetMode;

/** @implements Rule<Expr> */
#[HouseRule('scarlet.cacheKey', 'cache keys are not versioned.', 'remove the version segment and tolerate old payloads until TTL expiry.', RatchetMode::Zero)]
#[HouseRule('scarlet.cacheKeyLiteral', 'cache keys have an owner.', 'obtain the cache key from its domain owner.', RatchetMode::Zero)]
final class LiteralCacheKeyRule implements Rule
{
    public function getNodeType(): string
    {
        return Expr::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        // Keys are often built in an owner method, away from the Cache call, so a constant joined in as a segment is caught where it is joined.
        if ($node instanceof Expr\BinaryOp\Concat && $this->codeSegment($node, $scope)) {
            return [RuleErrorBuilder::message('House rule: cache keys are not versioned. Fix: remove the version segment and tolerate old payloads until TTL expiry.')->identifier('scarlet.cacheKey')->build()];
        }
        $key = CacheCall::key($node, $scope);
        $errors = [];
        if ($key === null) {
            return [];
        }
        if ($this->versioned($key)) {
            $errors[] = RuleErrorBuilder::message('House rule: cache keys are not versioned. Fix: remove the version segment and tolerate old payloads until TTL expiry.')->identifier('scarlet.cacheKey')->build();
        }
        if (CacheCall::containsLiteral($key)) {
            $errors[] = RuleErrorBuilder::message('House rule: cache keys have an owner. Fix: obtain the cache key from its domain owner.')->identifier('scarlet.cacheKeyLiteral')->build();
        }

        return $errors;
    }

    /** A number or class constant joined to a ':' segment: it changes only when the code does, so it versions the key. */
    private function codeSegment(Expr\BinaryOp\Concat $concat, Scope $scope): bool
    {
        $before = $this->edge($concat->left, right: true);
        $after = $this->edge($concat->right, right: false);

        // 'max:'.self::LIMIT is a validation rule, not a key.
        $validation = $before instanceof Scalar\String_ && preg_match('/(^|\|)(min|max|size|between|digits|digits_between|gt|gte|lt|lte|min_digits|max_digits|multiple_of|decimal):$/', $before->value) === 1;

        return (! $validation && $this->constant($after, $scope) && $before instanceof Scalar\String_ && str_ends_with($before->value, ':'))
            || ($this->constant($before, $scope) && $after instanceof Scalar\String_ && str_starts_with($after->value, ':'));
    }

    private function edge(Expr $expr, bool $right): Expr
    {
        while ($expr instanceof Expr\BinaryOp\Concat) {
            $expr = $right ? $expr->right : $expr->left;
        }

        return $expr;
    }

    private function constant(Expr $expr, Scope $scope): bool
    {
        if ($expr instanceof Scalar\Int_) {
            return true;
        }
        if (! $expr instanceof Expr\ClassConstFetch && ! $expr instanceof Expr\ConstFetch) {
            return false;
        }
        // A row id names data, not a code revision.
        if ($expr instanceof Expr\ClassConstFetch && $expr->name instanceof Node\Identifier && str_ends_with($expr->name->toString(), '_ID')) {
            return false;
        }
        $values = $scope->getType($expr)->getConstantScalarValues();

        return $values !== [] && array_filter($values, fn (mixed $value): bool => ! is_int($value) && preg_match('/^v?\d+$/', (string) $value) !== 1) === [];
    }

    private function versioned(Expr $key): bool
    {
        if ($key instanceof Scalar\String_) {
            return preg_match('/:v\d/', $key->value) === 1;
        }
        if ($key instanceof Expr\BinaryOp\Concat) {
            return $this->versioned($key->left) || $this->versioned($key->right);
        }
        if ($key instanceof Scalar\InterpolatedString) {
            foreach ($key->parts as $part) {
                if ($part instanceof Node\InterpolatedStringPart && preg_match('/:v\d/', $part->value) === 1) {
                    return true;
                }
            }
        }

        return false;
    }
}
