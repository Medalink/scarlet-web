<x-module :span="$span" :first="$first" :last="$last" id="h-hours" heading="Play time by hour">
  @if ($d['hours'])
  <x-hours-grid id="hours" label="Play time by weekday and hour of day, in UTC" :spec="$d['hours']" />
  <x-readout id="hours-out" :line="$d['hours']['summary'] ?? ['', '']" />
  <x-heat-key low="Quiet" high="Busiest" :zero="true" extra="Hours are UTC" />
  @else
  <x-note>No play time recorded yet.</x-note>
  @endif
</x-module>
