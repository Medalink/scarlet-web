<?php

namespace App\View\Pages;

use App\Models\Character;
use App\Support\ClassPage\ClassIcons;
use App\Support\ClassPage\ClassPage;
use App\Support\ClassPage\Figure;
use App\Support\ClassPage\IconMap;

/** The data of the class page view from the answer of App\Actions\Public\ClassPage\CharacterClassPage. */
final class ClassPageData
{
    private const int SHARED_FIGURES = 3;

    /**
     * @param  array{character: Character, owner: bool, page: ClassPage}  $r
     * @return array{page: ClassPage, icons: IconMap, title: string, description: string, canonical: string, index: bool}
     */
    public static function data(array $r): array
    {
        $page = $r['page'];
        $line = $page->character;
        $class = strtolower($page->name());
        $figures = implode(', ', array_map(
            fn (Figure $figure): string => $figure->label.' '.$figure->unit->figure($figure->value).($figure->of === null ? '' : ' '.$figure->of),
            array_slice($page->figures, 0, self::SHARED_FIGURES),
        ));

        return [
            'page' => $page,
            'icons' => ClassIcons::of($page),
            'title' => $line->name.' the '.$class,
            'description' => $line->name.' is a level '.$line->level.' '.$class.'. '.$figures.'.',
            'canonical' => $page->links->classPage ?? $page->links->character,
            'index' => ! $r['owner'],
        ];
    }
}
