<?php

namespace App\View\Pages;

use App\Support\Profession;
use App\View\Format;

/** The data of /professions: the professions with a page in a gathering and a crafting group, from the skill rows of App\Actions\Rollups\Professions. */
final class ProfessionsIndex
{
    /**
     * @param  list<array<string, mixed>>  $skills  kind, skill, skill_id, ups, best_rank, characters
     * @param  string  $query  the query every row link keeps, with its leading ?
     * @return array{title: string, sub: string, groups: list<array{title: string, items: list<array{name: string, href: string, characters: int, rank: int, ups: int}>}>, missing: list<array{name: string, href: string}>, most: int, ups: int}
     */
    public static function data(array $skills, string $query = ''): array
    {
        $byLine = [];
        foreach ($skills as $skill) {
            $byLine[(int) ($skill['skill_id'] ?? 0)] = $skill;
        }
        $groups = ['Gathering' => [], 'Crafting' => []];
        $missing = [];
        foreach (Profession::withPages() as $profession) {
            $skill = $byLine[(int) $profession->skillLine()] ?? null;
            $href = '/professions/'.$profession->slug().$query;
            if ($skill === null || $skill['characters'] === null) {
                $missing[] = ['name' => $profession->value, 'href' => $href];

                continue;
            }
            $groups[$profession->gatherKind() === null ? 'Crafting' : 'Gathering'][] = [
                'name' => $profession->value, 'href' => $href,
                'characters' => (int) $skill['characters'], 'rank' => (int) $skill['best_rank'], 'ups' => (int) $skill['ups'],
            ];
        }
        $shown = [];
        foreach ($groups as $title => $items) {
            usort($items, fn (array $a, array $b): int => [$b['characters'], $b['ups'], $a['name']] <=> [$a['characters'], $a['ups'], $b['name']]);
            if ($items !== []) {
                $shown[] = ['title' => $title, 'items' => $items];
            }
        }
        $all = array_merge(...array_column($shown, 'items'));

        return [
            'title' => 'Professions',
            'sub' => Format::count(count(Profession::withPages()), 'profession').', with the characters that practise each.',
            'groups' => $shown,
            'missing' => $missing,
            'most' => max(1, ...array_column($all, 'characters') ?: [1]),
            'ups' => max(1, ...array_column($all, 'ups') ?: [1]),
        ];
    }
}
