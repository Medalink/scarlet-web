<?php

namespace Scarlet\Analysis\Rules;

use DateTimeInterface;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Queue\ShouldQueue;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ArrayType;
use PHPStan\Type\BooleanType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\VerbosityLevel;
use Scarlet\Analysis\HouseRule;
use Scarlet\Analysis\RatchetMode;

/**
 * Laravel finds queue hooks with method_exists(), which ignores case and visibility, and only
 * the real queue calls them. Queue::fake() and the sync driver never build a payload.
 *
 * @implements Rule<InClassNode>
 */
#[HouseRule('scarlet.queueHookSignature', 'queued methods named like Laravel queue hooks keep their signature.', 'rename the method or match the signature.', RatchetMode::Zero)]
final class QueueHookSignatureRule implements Rule
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $class = $node->getClassReflection();
        if (! $class->implementsInterface(ShouldQueue::class)) {
            return [];
        }

        $errors = [];
        foreach (self::hooks() as $name => [$returns, $parameters]) {
            if (! $class->hasMethod($name)) {
                continue;
            }

            $method = $class->getMethod($name, $scope);
            $variant = $method->getVariants()[0];
            $required = count(array_filter($variant->getParameters(), fn ($parameter): bool => ! $parameter->isOptional()));
            $native = $variant->getNativeReturnType();
            $declared = ! ($native instanceof MixedType) || $native->isExplicitMixed();

            if ($method->isPublic() && $required <= $parameters && (! $declared || $returns->isSuperTypeOf($native)->yes())) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message('House rule: a queued class method named like a Laravel queue hook is public and keeps its signature. Fix: '.$method->getName().'() must be public, take at most '.$parameters.' required parameters and return '.$returns->describe(VerbosityLevel::typeOnly()).', or be renamed.')
                ->identifier('scarlet.queueHookSignature')
                ->line($this->line($node, $method->getName()))
                ->build();
        }

        return $errors;
    }

    private function line(InClassNode $node, string $name): int
    {
        foreach ($node->getOriginalNode()->getMethods() as $method) {
            if (strcasecmp($method->name->toString(), $name) === 0) {
                return $method->getLine();
            }
        }

        return $node->getOriginalNode()->getLine();
    }

    /** @return array<string, array{Type, int}> hook name => accepted return type, most required parameters */
    private static function hooks(): array
    {
        $int = new IntegerType;
        $string = new StringType;
        $null = new NullType;
        $array = new ArrayType(new MixedType, new MixedType);
        $date = new ObjectType(DateTimeInterface::class);

        return [
            'displayName' => [$string, 0],
            'tags' => [$array, 1],
            'tries' => [TypeCombinator::union($int, $null), 0],
            'backoff' => [TypeCombinator::union($int, $array, $date, $null), 0],
            'retryUntil' => [TypeCombinator::union($date, $int, $null), 0],
            'middleware' => [$array, 0],
            'uniqueId' => [TypeCombinator::union($string, $int), 0],
            'uniqueFor' => [$int, 0],
            'uniqueVia' => [TypeCombinator::union(new ObjectType(Repository::class), $null), 0],
            'shouldFailOnTimeout' => [new BooleanType, 0],
            'failed' => [new MixedType, 2],
        ];
    }
}
