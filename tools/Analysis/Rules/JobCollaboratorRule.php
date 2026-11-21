<?php

namespace Scarlet\Analysis\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Scarlet\Analysis\HouseRule;
use Scarlet\Analysis\RatchetMode;

/** @implements Rule<Node\Stmt\ClassMethod> */
#[HouseRule('scarlet.jobCollaborator', 'queued constructors carry serializable input.', 'inject collaborators into handle() instead.', RatchetMode::Zero)]
final class JobCollaboratorRule implements Rule
{
    public function getNodeType(): string
    {
        return Node\Stmt\ClassMethod::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $class = $scope->getClassReflection();
        if ($node->name->toLowerString() !== '__construct' || ! str_starts_with($class?->getName() ?? '', 'App\\Jobs\\') || ! $class->implementsInterface('Illuminate\\Contracts\\Queue\\ShouldQueue')) {
            return [];
        }
        $invalid = false;
        foreach ($node->params as $parameter) {
            if ($parameter->type === null) {
                continue;
            }
            $type = $scope->getFunctionType($parameter->type, isNullable: false, isVariadic: false);
            foreach ($type->getObjectClassReflections() as $reflection) {
                $name = $reflection->getName();
                if ($reflection->isInterface() || str_starts_with($name, 'App\\Services\\') || str_starts_with($name, 'App\\Actions\\') || str_starts_with($name, 'Illuminate\\Http\\Client\\')) {
                    $invalid = true;
                }
            }
        }
        if (! $invalid) {
            return [];
        }

        return [RuleErrorBuilder::message('House rule: queued constructors carry serializable input. Fix: inject collaborators into handle() instead.')->identifier('scarlet.jobCollaborator')->build()];
    }
}
