<?php

namespace Scarlet\Analysis\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Scarlet\Analysis\HouseRule;
use Scarlet\Analysis\RatchetMode;

/** @implements Rule<FileNode> */
#[HouseRule('scarlet.fileLength', 'files stay within 500 lines.', 'split the file by responsibility.', RatchetMode::Zero)]
final class FileLengthRule implements Rule
{
    public function getNodeType(): string
    {
        return FileNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $lines = count(file($scope->getFile()) ?: []);
        $errors = [];
        for ($line = 500; $line < $lines; $line += 100) {
            $errors[] = RuleErrorBuilder::message('House rule: files stay within 500 lines. Fix: split the file by responsibility.')->identifier('scarlet.fileLength')->line(1)->build();
        }

        return $errors;
    }
}
