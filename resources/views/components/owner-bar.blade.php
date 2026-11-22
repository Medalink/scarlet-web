{{-- The bar only the signed-in owner sees on a character page. state: label and whether it is on; facts: strings;
     others: names of the owner's other characters, each linked to its page; link: ['href', 'label']; visitor: href of the
     page as a visitor sees it, or null; the switch is the public switch.
     Other attributes (wire:change) go to the switch. --}}
@props(['state', 'on' => false, 'facts', 'others' => [], 'link', 'visitor' => null, 'switchLabel', 'public' => false])
<div class="own" aria-label="Only you see this bar">
  <x-state :on="$on">{{ $state }}</x-state>@foreach ($facts as $fact)<span>{{ $fact }}</span>@endforeach
@if ($others !== [])
  <nav class="alts" aria-label="Your other characters">@foreach ($others as $other)<a href="/c/{{ rawurlencode($other) }}">{{ \App\View\Format::characterName($other) }}</a>@endforeach<a href="/account/characters">All characters</a></nav>
@endif
  <span class="sp">@if ($visitor)<a href="{{ $visitor }}">View as visitor</a>@endif<a href="{{ $link['href'] }}">{{ $link['label'] }}</a><x-switch :label="$switchLabel" :checked="$public" {{ $attributes }} /></span>
</div>
