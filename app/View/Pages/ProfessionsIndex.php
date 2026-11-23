<?php

namespace App\View\Pages;

use App\Support\Profession;
use App\View\Format;

/** The data of /professions: a row for each profession with a page, from the skill rows of App\Actions\Rollups\Professions. */
final class ProfessionsIndex
{
    /**
     * @param  list<array<string, mixed>>  $skills  kind, skill, skill_id, ups, best_rank, characters
     * @return array{title: string, sub: string, columns: list<array<string, mixed>>, rows: list<array<string, mixed>>}
     */
    public static function data(array $skills): array
    {
        $byLine = [];
        foreach ($skills as $skill) {
            $byLine[(int) ($skill['skill_id'] ?? 0)] = $skill;
        }
        $rows = [];
        foreach (Profession::withPages() as $profession) {
            $skill = $byLine[(int) $profession->skillLine()] ?? null;
            $rows[] = ['cells' => [
                ['text' => $profession->value, 'href' => '/professions/'.$profession->slug()],
                $skill === null ? Format::TOO_FEW : Format::number((int) $skill['characters']),
                $skill === null ? '–' : Format::number((int) $skill['best_rank']),
                $skill === null ? '–' : Format::number((int) $skill['ups']),
            ]];
        }

        return [
            'title' => 'Professions',
            'sub' => Format::count(count($rows), 'profession').', with the characters that practise each.',
            'columns' => [['label' => 'Profession'], ['label' => 'Characters', 'numeric' => true], ['label' => 'Highest rank', 'numeric' => true], ['label' => 'Skill-ups', 'numeric' => true]],
            'rows' => $rows,
        ];
    }
}
