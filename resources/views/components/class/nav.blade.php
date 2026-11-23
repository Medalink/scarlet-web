{{-- The line of links under a character's head: links is App\Support\ClassPage\Links, name the character, className the class's name, current character, class or battlegrounds. It is drawn only when the character has a second page to go to. --}}
@props(['links', 'name', 'className' => '', 'current'])
@if ($links->classPage || $links->battlegrounds)
<nav class="trades pages" aria-label="Pages of {{ $name }}"><a href="{{ $links->character }}"@if ($current === 'character') aria-current="page"@endif>Character</a>@if ($links->classPage)<a href="{{ $links->classPage }}"@if ($current === 'class') aria-current="page"@endif>{{ $className }}</a>@endif @if ($links->battlegrounds)<a href="{{ $links->battlegrounds }}"@if ($current === 'battlegrounds') aria-current="page"@endif>Battlegrounds</a>@endif</nav>
@endif
