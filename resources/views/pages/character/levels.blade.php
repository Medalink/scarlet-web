<x-module :span="$span" :first="$first" :last="$last" :level="$level" id="h-levels" heading="Levels">
  @if ($d['levels'])
  <x-plot id="levels" label="Hours {{ $d['name'] }} spent in each level, each bar filled by the share of its experience from kills, dungeon kills, quests, exploring and other, with the median of its ruleset as a tick where at least two characters completed the level and a cross for each death. Use the left and right arrow keys to read a level." :spec="$d['levels']['spec']" />
  <x-readout id="levels-out" :line="$d['levels']['line']" />
  <x-key :items="$d['levels']['key']" />
  @else
  <x-note>Fewer than two completed levels are recorded.</x-note>
  @endif
</x-module>
