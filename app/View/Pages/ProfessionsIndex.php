<?php

namespace App\View\Pages;

use App\Support\Profession;
use App\View\Format;

/** The data of /professions: a row for each profession with a page, from the skill rows of App\Actions\Rollups\Professions. */
final class ProfessionsIndex
{
    /**
     * @param  list<array<string, mixed>>  $skills  kind, skill, skill_id, ups, best_rank, characters
     * @param  string  $query  the query every row link keeps, with its leading ?
     * @return array{title: string, items: list<array{name: string, href: string, characters: int|null, rank: int|null, ups: int|null}>, sub: string, columns: list<array<string, mixed>>, rows: list<array<string, mixed>>}
     */
    public static function data(array $skills, string $query = ''): array
    {
        $byLine = [];
        foreach ($skills as $skill) {
            $byLine[(int) ($skill['skill_id'] ?? 0)] = $skill;
        }
        $rows = [];
        $items = [];
        foreach (Profession::withPages() as $profession) {
            $skill = $byLine[(int) $profession->skillLine()] ?? null;
            $items[] = [
                'name' => $profession->value,
                'href' => '/professions/'.$profession->slug().$query,
                'characters' => $skill === null ? null : (int) $skill['characters'],
                'rank' => $skill === null ? null : (int) $skill['best_rank'],
                'ups' => $skill === null ? null : (int) $skill['ups'],
            ];
            $rows[] = ['cells' => [
                ['text' => $profession->value, 'href' => '/professions/'.$profession->slug().$query],
                $skill === null ? Format::TOO_FEW : Format::number((int) $skill['characters']),
                $skill === null ? '–' : Format::number((int) $skill['best_rank']),
                $skill === null ? '–' : Format::number((int) $skill['ups']),
            ]];
        }

        return [
            'title' => 'Twelve professions',
            'items' => $items,
            'sub' => Format::count(count($rows), 'profession').', with the characters that practise each.',
            'columns' => [['label' => 'Profession'], ['label' => 'Characters', 'numeric' => true], ['label' => 'Highest rank', 'numeric' => true], ['label' => 'Skill-ups', 'numeric' => true]],
            'rows' => $rows,
        ];
    }
}
