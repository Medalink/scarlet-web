{{-- The bar only the signed-in owner sees on a character page. state: label and whether it is on; facts: strings;
     link: ['href', 'label']; the switch is the public switch. Other attributes (wire:change) go to the switch. --}}
@props(['state', 'on' => false, 'facts', 'link', 'switchLabel', 'public' => false])
<div class="own" aria-label="Only you see this bar">
  <x-state :on="$on">{{ $state }}</x-state>@foreach ($facts as $fact)<span>{{ $fact }}</span>@endforeach
  <span class="sp"><a href="{{ $link['href'] }}">{{ $link['label'] }}</a><x-switch :label="$switchLabel" :checked="$public" {{ $attributes }} /></span>
</div>
