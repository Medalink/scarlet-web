<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Public\RulesetComparison;
use App\Actions\Public\RulesetOverview;
use App\Actions\Public\RulesetSeries;
use App\Http\Requests\Api\PublicRequest;
use App\Support\Series;
use Illuminate\Http\JsonResponse;

/** Headline numbers, the rulesets and their series. */
class StatsController extends PublicController
{
    public function stats(PublicRequest $request, RulesetOverview $overview): JsonResponse
    {
        return $this->overview($overview->handle($request->rulesetSlug(), $request->serverFilter()), 'The ruleset');
    }

    public function ruleset(PublicRequest $request, RulesetOverview $overview, string $slug): JsonResponse
    {
        return $this->overview($overview->handle($slug), 'The ruleset');
    }

    public function rulesets(PublicRequest $request, RulesetComparison $comparison): JsonResponse
    {
        $answer = $comparison->handle();
        $rows = [];
        foreach ($answer['rulesets'] as $ruleset) {
            $summary = collect($answer['rows'])->firstWhere('ruleset_id', $ruleset['id']) ?? [];
            unset($summary['ruleset_id']);
            $rows[] = [...$ruleset, ...$summary, 'levelling' => $answer['curves'][$ruleset['id']] ?? []];
        }

        return $this->respond($rows);
    }

    public function series(PublicRequest $request, RulesetSeries $read, string $slug, string $series): JsonResponse
    {
        $kind = Series::tryFrom($series);
        if ($kind === null) {
            $this->missing('The series');
        }
        $answer = $read->handle($slug === 'all' ? null : $slug, $kind);
        if ($answer === null) {
            $this->missing('The ruleset');
        }

        return $this->respond($answer, ['ruleset' => $slug, 'series' => $kind->value]);
    }

    /** @param  array<string, mixed>|null  $answer */
    private function overview(?array $answer, string $what): JsonResponse
    {
        if ($answer === null) {
            $this->missing($what);
        }

        return $this->respond(
            ['totals' => $answer['totals'], 'coverage' => $answer['coverage'], 'rulesets' => $answer['rulesets']],
            [...$answer['scope']->describe(), 'min_characters' => (int) config('scarlet.min_characters')],
        );
    }
}
