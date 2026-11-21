<?php

namespace Scarlet\Analysis\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Scarlet\Analysis\HouseRule;
use Scarlet\Analysis\RatchetMode;

/** @implements Rule<Node\Stmt\Class_> */
#[HouseRule('scarlet.serviceShape', 'services are integration surfaces.', 'one public method is an Action, move it to app/Actions.', RatchetMode::Zero)]
final class ServiceShapeRule implements Rule
{
    public function getNodeType(): string
    {
        return Node\Stmt\Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! str_starts_with($scope->getNamespace() ?? '', 'App\\Services')) {
            return [];
        }
        $methods = array_filter($node->getMethods(), fn ($method): bool => $method->isPublic() && strtolower($method->name->toString()) !== '__construct');
        if (count($methods) !== 1) {
            return [];
        }

        return [RuleErrorBuilder::message('House rule: services are integration surfaces. Fix: one public method is an Action, move it to app/Actions.')->identifier('scarlet.serviceShape')->build()];
    }
}
