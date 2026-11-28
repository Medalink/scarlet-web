{{-- A game icon of the class page at its final size. icon: App\Support\ClassPage\Icon or null; icons: its IconMap. A row whose icon has no file shows no icon. --}}
@props(['icon', 'icons', 'any' => false])
@if ($icon && ($url = $icons->url($icon)))<img class="ic" src="{{ $url }}" alt="" width="26" height="26" loading="lazy"> @endif