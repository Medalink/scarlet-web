<?php

namespace App\Actions\Account;

use App\Actions\Public\Character\SkillRanks;
use App\Support\Profession;

class ShowCharacterProfessions
{
    public function __construct(private readonly SkillRanks $ranks) {}

    /**
     * The highest rank of each profession a character reached, by character id.
     *
     * @param  list<int>  $characterIds
     * @return array<int, array<string, array{rank: int, cap: int}>> character id → profession → rank
     */
    public function handle(array $characterIds): array
    {
        $answer = [];
        foreach ($characterIds as $id) {
            $professions = [];
            foreach ($this->ranks->handle($id) as $skill) {
                if (Profession::tryFrom($skill['skill']) !== null) {
                    $professions[$skill['skill']] = ['rank' => $skill['rank'], 'cap' => $skill['cap']];
                }
            }
            $answer[$id] = $professions;
        }

        return $answer;
    }
}
