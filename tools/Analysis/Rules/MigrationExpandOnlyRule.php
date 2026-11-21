<?php

namespace Scarlet\Analysis\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Scarlet\Analysis\HouseRule;
use Scarlet\Analysis\RatchetMode;

/** @implements Rule<FileNode> */
#[HouseRule('scarlet.migrationUnsafe', 'migrations expand safely online.', 'use nullable or defaulted additions; defer contract changes and build indexes outside a transaction.', RatchetMode::Zero)]
final class MigrationExpandOnlyRule implements Rule
{
    private const COLUMNS = ['biginteger', 'binary', 'boolean', 'char', 'date', 'datetime', 'datetimetz', 'decimal', 'double', 'enum', 'float', 'foreignid', 'foreignuuid', 'foreignulid', 'geometry', 'geography', 'integer', 'ipaddress', 'json', 'jsonb', 'longtext', 'macaddress', 'mediuminteger', 'mediumtext', 'smallinteger', 'string', 'text', 'time', 'timetz', 'timestamp', 'timestamptz', 'tinyinteger', 'tinytext', 'unsignedbiginteger', 'unsignedinteger', 'uuid', 'ulid', 'vector', 'year'];

    public function getNodeType(): string
    {
        return FileNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $filename = basename($scope->getFile());
        if (! preg_match('/^\d{4}_\d{2}_\d{2}_/', $filename)) {
            return [];
        }
        $finder = new NodeFinder;
        $nodes = $node->getNodes();
        $outsideTransaction = false;
        foreach ($finder->findInstanceOf($nodes, Node\Stmt\PropertyProperty::class) as $property) {
            if ($property->name->toString() === 'withinTransaction' && $property->default instanceof Expr\ConstFetch && $property->default->name->toLowerString() === 'false') {
                $outsideTransaction = true;
            }
        }
        $lines = [];
        foreach ($finder->findInstanceOf($nodes, Expr\StaticCall::class) as $call) {
            if (! $call->class instanceof Node\Name || ! str_ends_with($call->class->toString(), 'Schema') || ! $call->name instanceof Node\Identifier || $call->isFirstClassCallable()) {
                continue;
            }
            $name = $call->name->toLowerString();
            if (in_array($name, ['drop', 'dropifexists', 'droptable', 'rename'], strict: true)) {
                $lines[] = $call->getStartLine();
            }
            if ($name !== 'table') {
                continue;
            }
            $closure = $call->getArgs()[1]->value ?? null;
            if (! $closure instanceof Expr\Closure && ! $closure instanceof Expr\ArrowFunction) {
                continue;
            }
            $parameter = $closure->params[0]->var ?? null;
            if (! $parameter instanceof Expr\Variable || ! is_string($parameter->name)) {
                continue;
            }
            $calls = $finder->findInstanceOf($closure, Expr\MethodCall::class);
            $children = [];
            foreach ($calls as $method) {
                $children[spl_object_id($method->var)] = true;
            }
            foreach ($calls as $method) {
                if (isset($children[spl_object_id($method)])) {
                    continue;
                }
                $names = [];
                $receiver = $method;
                while ($receiver instanceof Expr\MethodCall && $receiver->name instanceof Node\Identifier) {
                    $names[] = $receiver->name->toLowerString();
                    $receiver = $receiver->var;
                }
                if (! $receiver instanceof Expr\Variable || $receiver->name !== $parameter->name) {
                    continue;
                }
                $unsafe = array_intersect($names, ['dropcolumn', 'renamecolumn', 'droptable', 'rename', 'change', 'unique']) !== [];
                $column = array_intersect($names, self::COLUMNS) !== [];
                $safeDefault = array_intersect($names, ['nullable', 'default']) !== [];
                $index = array_intersect($names, ['index', 'fulltext', 'spatialindex']) !== [];
                if ($unsafe || ($column && ! $safeDefault) || ($index && ! $outsideTransaction)) {
                    $lines[] = $method->getStartLine();
                }
            }
        }
        foreach ($finder->findInstanceOf($nodes, Expr\MethodCall::class) as $call) {
            if ($call->name instanceof Node\Identifier && $call->name->toLowerString() === 'enum') {
                $lines[] = $call->getStartLine();
            }
        }
        sort($lines);

        return array_map(fn (int $line) => RuleErrorBuilder::message('House rule: migrations expand safely online. Fix: use nullable or defaulted additions; defer contract changes and build indexes outside a transaction.')->identifier('scarlet.migrationUnsafe')->line($line)->build(), array_values(array_unique($lines)));
    }
}
