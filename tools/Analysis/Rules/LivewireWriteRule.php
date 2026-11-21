<?php

namespace Scarlet\Analysis\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use Scarlet\Analysis\HouseRule;
use Scarlet\Analysis\RatchetMode;

/** @implements Rule<Expr> */
#[HouseRule('scarlet.livewireWrite', 'Livewire delegates domain writes.', 'move the write and its invariants into an Action.', RatchetMode::Zero)]
final class LivewireWriteRule implements Rule
{
    public function getNodeType(): string
    {
        return Expr::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! str_starts_with($scope->getClassReflection()?->getName() ?? '', 'App\\Livewire\\')) {
            return [];
        }
        if (! $node instanceof Expr\StaticCall && ! $node instanceof Expr\MethodCall) {
            return [];
        }
        if (! $node->name instanceof Node\Identifier) {
            return [];
        }
        $name = strtolower($node->name->toString());
        $matches = false;
        if ($node instanceof Expr\StaticCall && $node->class instanceof Node\Name) {
            $class = $scope->resolveName($node->class);
            $model = (new ObjectType('Illuminate\\Database\\Eloquent\\Model'))->isSuperTypeOf(new ObjectType($class))->yes();
            $matches = $model && in_array($name, ['create', 'updateorcreate', 'increment', 'decrement', 'destroy'], strict: true);
        }
        if ($node instanceof Expr\MethodCall && in_array($name, ['update', 'save', 'delete', 'forcedelete', 'increment', 'decrement', 'insert', 'updateorcreate', 'create'], strict: true)) {
            $receiver = $scope->getType($node->var);
            foreach (['Illuminate\\Database\\Eloquent\\Model', 'Illuminate\\Database\\Eloquent\\Builder', 'Illuminate\\Database\\Query\\Builder', 'Illuminate\\Database\\Eloquent\\Relations\\Relation'] as $type) {
                if ((new ObjectType($type))->isSuperTypeOf($receiver)->yes()) {
                    $matches = true;
                }
            }
        }
        if (! $matches) {
            return [];
        }

        return [RuleErrorBuilder::message('House rule: Livewire delegates domain writes. Fix: move the write and its invariants into an Action.')->identifier('scarlet.livewireWrite')->build()];
    }
}
