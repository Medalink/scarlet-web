@php($both = $groups['rolls'] && $groups['received'])
@if ($groups['rolls'])
<x-module :span="$both ? 'w5' : 'w12'" :first="true" :last="! $both" id="h-r" heading="Rolls in groups">
  <x-rank :rows="$groups['rolls']['rows']" />
  <p class="note">{{ $groups['rolls']['note'] }}</p>
</x-module>
@endif
@if ($groups['received'])
<x-module :span="$both ? 'w7' : 'w12'" :first="! $both" :last="true" id="h-c" heading="Who received it">
  <x-table :rows="$groups['received']['rows']" :columns="$groups['received']['columns']" style="margin-top:1.1rem" />
  <p class="note">{{ $groups['received']['note'] }}</p>
</x-module>
@endif
