{{-- The scope of a hub for a signed-in viewer: Everyone, You vs everyone, Only you, and for You vs everyone the character
     compared. `names` are the account's characters, `as` the one compared, `query` the other parameters the link keeps. --}}
@props(['choice', 'path', 'names', 'as' => null, 'query' => []])
@if ($names !== [])
@php
    $as = $as ?? $names[0];
    $href = fn (array $more): string => $path.($query + $more === [] ? '' : '?'.http_build_query($query + $more));
@endphp
<x-seg label="Scope" :items="[
    ['label' => 'Everyone', 'href' => $href([]), 'on' => $choice === 'everyone'],
    ['label' => 'You vs everyone', 'href' => $href(['scope' => 'compare', 'as' => $as]), 'on' => $choice === 'compare'],
    ['label' => 'Only you', 'href' => $href(['scope' => 'mine']), 'on' => $choice === 'mine'],
]" />
@if ($choice === 'compare' && count($names) > 1)<x-pick label="Character" :options="array_map(fn (string $name): array => [$name, $href(['scope' => 'compare', 'as' => $name])], $names)" :selected="$as" />@endif
@endif
