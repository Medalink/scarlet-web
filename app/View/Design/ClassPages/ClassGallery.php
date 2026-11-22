<?php

namespace App\View\Design\ClassPages;

use App\Support\ClassPage\ClassBand;
use App\Support\ClassPage\ClassIcons;
use App\Support\ClassPage\ClassPage;
use App\Support\ClassPage\IconMap;

/** The class pages of the gallery: each class's page object over invented numbers, the icons it asks for and the band it gives the character page. */
final class ClassGallery
{
    /** Gallery page → the class it draws. */
    public const array PAGES = ['class' => 'warlock', 'class-rogue' => 'rogue', 'class-shaman' => 'shaman'];

    /**
     * @return array{page: ClassPage, band: ClassBand, icons: IconMap, title: string}
     */
    public static function data(string $page): array
    {
        $object = match (self::PAGES[$page]) {
            'rogue' => RoguePage::page(),
            'shaman' => ShamanPage::page(),
            default => WarlockPage::page(),
        };
        $band = new ClassBand($object->class, '#', array_slice($object->figures, 0, 5), $object->character->icon);

        return [
            'page' => $object,
            'band' => $band,
            'icons' => ClassIcons::of($object),
            'title' => $object->character->name.' the '.strtolower($object->name()),
        ];
    }
}
