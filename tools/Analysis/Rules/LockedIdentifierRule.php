<?php

namespace Scarlet\Analysis\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\IntegerType;
use PHPStan\Type\StringType;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;
use Scarlet\Analysis\HouseRule;
use Scarlet\Analysis\RatchetMode;

/** @implements Rule<Node\Stmt\Property> */
#[HouseRule('scarlet.unlockedId', 'component identifiers are locked.', 'add #[Locked] and authorize the identifier in each action.', RatchetMode::Zero)]
final class LockedIdentifierRule implements Rule
{
    public function getNodeType(): string
    {
        return Node\Stmt\Property::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! str_starts_with($scope->getClassReflection()?->getName() ?? '', 'App\\Livewire\\') || ! $node->isPublic() || $node->isStatic() || $node->type === null) {
            return [];
        }
        foreach ($node->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if ($scope->resolveName($attribute->name) === 'Livewire\\Attributes\\Locked') {
                    return [];
                }
            }
        }
        $type = $scope->getFunctionType($node->type, isNullable: false, isVariadic: false);
        $scalar = TypeCombinator::removeNull($type);
        if (! $scalar->isInteger()->yes() && ! $scalar->isString()->yes() && ! (new UnionType([new IntegerType, new StringType]))->isSuperTypeOf($scalar)->yes()) {
            return [];
        }
        $invalid = false;
        foreach ($node->props as $property) {
            if (str_ends_with($property->name->toString(), 'Id')) {
                $invalid = true;
            }
        }
        if (! $invalid) {
            return [];
        }

        return [RuleErrorBuilder::message('House rule: component identifiers are locked. Fix: add #[Locked] and authorize the identifier in each action.')->identifier('scarlet.unlockedId')->build()];
    }
}
