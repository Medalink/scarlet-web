<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Public\CharacterOverview;
use App\Http\Requests\Api\PublicRequest;
use App\Models\Character;
use Illuminate\Http\JsonResponse;

/** A public character and its series. Private characters answer 404 to everyone, their owner included: the API has no sign-in. */
class CharacterController extends PublicController
{
    /** The series of a character and the keys of the overview each one answers. */
    public const array SERIES = [
        'levelling' => ['pace'],
        'xp' => ['xp'],
        'deaths' => ['deaths'],
        'dungeons' => ['dungeons'],
        'activity' => ['days', 'week'],
        'sessions' => ['sessions'],
        'gold' => ['gold'],
    ];

    public function show(PublicRequest $request, CharacterOverview $overview, string $name): JsonResponse
    {
        $answer = $overview->handle($name, null);
        if ($answer === null) {
            $this->missing('The character');
        }

        return $this->respond([...$this->profile($answer), 'week' => $answer['week'], 'distance' => $answer['distance'], 'feats' => $answer['feats'], 'standings' => $answer['standings']], ['economy' => $answer['economy']]);
    }

    public function series(PublicRequest $request, CharacterOverview $overview, string $name, string $series): JsonResponse
    {
        if (! isset(self::SERIES[$series])) {
            $this->missing('The series');
        }
        $answer = $overview->handle($name, null);
        if ($answer === null || ($series === 'gold' && ! $answer['economy'])) {
            $this->missing($answer === null ? 'The character' : 'The series');
        }

        return $this->respond(array_intersect_key($answer, array_flip(self::SERIES[$series])), ['series' => $series]);
    }

    /**
     * @param  array<string, mixed>  $answer
     * @return array<string, mixed>
     */
    private function profile(array $answer): array
    {
        /** @var Character $character */
        $character = $answer['character'];
        /** @var array<string, mixed>|null $ruleset */
        $ruleset = $answer['ruleset'];

        return [
            'name' => $character->name, 'race' => $character->race, 'class' => $character->class, 'faction' => $character->faction,
            'level' => $character->level, 'played_seconds' => $character->played_known ? $character->played_seconds : null, 'recorded_seconds' => $character->recorded_seconds,
            'first_level' => $character->first_level, 'recorded_since' => $character->first_seen_at?->toIso8601ZuluString(), 'whole_life' => $character->whole_life, 'ruleset' => $ruleset['slug'] ?? null,
            'last_seen_at' => $character->last_seen_at?->toIso8601ZuluString(),
        ];
    }
}
