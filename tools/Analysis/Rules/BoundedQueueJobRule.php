<?php

namespace Scarlet\Analysis\Rules;

use App\Contracts\BoundedJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Scarlet\Analysis\HouseRule;
use Scarlet\Analysis\RatchetMode;

/** @implements Rule<InClassNode> */
#[HouseRule('scarlet.unboundedJob', 'queued jobs declare bounded execution.', 'implement App\Contracts\BoundedJob with public int $timeout, public int $tries and public int $memoryMb.', RatchetMode::Zero)]
final class BoundedQueueJobRule implements Rule
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $class = $node->getClassReflection();
        if (! str_starts_with($class->getName(), 'App\\Jobs\\') || ! $class->implementsInterface(ShouldQueue::class)) {
            return [];
        }

        if ($class->implementsInterface(BoundedJob::class)) {
            return [];
        }

        return [RuleErrorBuilder::message('House rule: queued jobs declare bounded execution. Fix: implement App\Contracts\BoundedJob with public int $timeout, public int $tries and public int $memoryMb.')->identifier('scarlet.unboundedJob')->build()];
    }
}
