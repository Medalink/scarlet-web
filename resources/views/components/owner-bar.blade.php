{{-- The bar only the signed-in owner sees on a character page. state: label and whether it is on; facts: strings;
     others: names of the owner's other characters, each linked to its page; link: ['href', 'label']; the switch is the public switch.
     Other attributes (wire:change) go to the switch. --}}
@props(['state', 'on' => false, 'facts', 'others' => [], 'link', 'switchLabel', 'public' => false])
<div class="own" aria-label="Only you see this bar">
  <x-state :on="$on">{{ $state }}</x-state>@foreach ($facts as $fact)<span>{{ $fact }}</span>@endforeach
@if ($others !== [])
  <nav class="alts" aria-label="Your other characters">@foreach ($others as $other)<a href="/c/{{ rawurlencode($other) }}">{{ $other }}</a>@endforeach<a href="/account/characters">All characters</a></nav>
@endif
  <span class="sp"><a href="{{ $link['href'] }}">{{ $link['label'] }}</a><x-switch :label="$switchLabel" :checked="$public" {{ $attributes }} /></span>
</div>
