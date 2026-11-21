<x-module :span="$span" :first="$first" :last="$last" id="h-days" heading="Play time by day">
  @if ($d['days'])
  <x-day-heat id="heat" :spec="$d['days']['spec']" />
  <x-heat-key low="0" high="over 4h" />
  <x-readout id="days-out" :line="$d['days']['line']" />
  @else
  <x-note>No play time recorded yet.</x-note>
  @endif
</x-module>
