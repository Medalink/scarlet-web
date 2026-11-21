<?php

namespace Scarlet\Analysis\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Scarlet\Analysis\HouseRule;
use Scarlet\Analysis\RatchetMode;

/** @implements Rule<ClassMethod> */
#[HouseRule('scarlet.migrationDown', 'new migrations are forward-only.', 'remove down() and correct schema with a new migration.', RatchetMode::Zero)]
final class ForwardOnlyMigrationRule implements Rule
{
    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! preg_match('/^\d{4}_\d{2}_\d{2}_/', basename($scope->getFile()))) {
            return [];
        }
        if (strtolower($node->name->toString()) !== 'down' || ! $scope->getClassReflection()?->isSubclassOf('Illuminate\\Database\\Migrations\\Migration')) {
            return [];
        }

        return [RuleErrorBuilder::message('House rule: new migrations are forward-only. Fix: remove down() and correct schema with a new migration.')->identifier('scarlet.migrationDown')->build()];
    }
}
