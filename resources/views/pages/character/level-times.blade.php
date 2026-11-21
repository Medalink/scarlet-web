<x-module :span="$span" :first="$first" :last="$last" :level="$level" id="h-pace" heading="Time in each level">
  @if ($d['levelTimes'])
  <x-plot id="level-times" label="Hours {{ $d['name'] }} spent in each level it has left, with the median of its ruleset as a tick where at least two characters completed the level." :spec="$d['levelTimes']['spec']" />
  <x-key :items="$d['levelTimes']['key']" />
  @else
  <x-note>Fewer than two completed levels are recorded.</x-note>
  @endif
</x-module>
