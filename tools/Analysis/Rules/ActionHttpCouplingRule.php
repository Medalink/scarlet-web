<?php

namespace Scarlet\Analysis\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use Scarlet\Analysis\HouseRule;
use Scarlet\Analysis\RatchetMode;

/** @implements Rule<ClassMethod> */
#[HouseRule('scarlet.actionHttp', 'Actions accept domain inputs and return domain results.', 'move Request, Component and Response handling to the HTTP boundary.', RatchetMode::Zero)]
final class ActionHttpCouplingRule implements Rule
{
    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! str_starts_with($scope->getClassReflection()?->getName() ?? '', 'App\\Actions\\')) {
            return [];
        }
        foreach ($node->params as $parameter) {
            if ($parameter->type === null) {
                continue;
            }
            $type = $scope->getFunctionType($parameter->type, isNullable: false, isVariadic: false);
            foreach (['Illuminate\\Http\\Request', 'Livewire\\Component'] as $forbidden) {
                if ((new ObjectType($forbidden))->isSuperTypeOf($type)->yes()) {
                    return $this->error();
                }
            }
        }
        if ($node->returnType !== null) {
            $type = $scope->getFunctionType($node->returnType, isNullable: false, isVariadic: false);
            if ((new ObjectType('Symfony\\Component\\HttpFoundation\\Response'))->isSuperTypeOf($type)->yes()) {
                return $this->error();
            }
        }

        return [];
    }

    /** @return list<IdentifierRuleError> */
    private function error(): array
    {
        return [RuleErrorBuilder::message('House rule: Actions accept domain inputs and return domain results. Fix: move Request, Component and Response handling to the HTTP boundary.')->identifier('scarlet.actionHttp')->build()];
    }
}
