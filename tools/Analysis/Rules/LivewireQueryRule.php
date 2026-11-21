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
#[HouseRule('scarlet.livewireQuery', 'Livewire delegates domain reads.', 'move the query into a read Action.', RatchetMode::Zero)]
final class LivewireQueryRule implements Rule
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
            $matches = ($model && ($name === 'query' || str_starts_with($name, 'where'))) || ($class === 'Illuminate\\Support\\Facades\\DB' && in_array($name, ['table', 'select'], strict: true));
        }
        if ($node instanceof Expr\MethodCall && in_array($name, ['get', 'first', 'paginate'], strict: true)) {
            $receiver = $scope->getType($node->var);
            foreach (['Illuminate\\Database\\Eloquent\\Builder', 'Illuminate\\Database\\Eloquent\\Relations\\Relation'] as $type) {
                if ((new ObjectType($type))->isSuperTypeOf($receiver)->yes()) {
                    $matches = true;
                }
            }
        }
        if (! $matches) {
            return [];
        }

        return [RuleErrorBuilder::message('House rule: Livewire delegates domain reads. Fix: move the query into a read Action.')->identifier('scarlet.livewireQuery')->build()];
    }
}
