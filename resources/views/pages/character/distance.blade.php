<x-module :span="$span" :first="$first" :last="$last" :level="$level" id="h-distance" :heading="'Distance by travel mode'.$d['since']">
  @if ($d['modes'])
  <x-stack-bar :segments="$d['modes']['segments']" :label="$d['modes']['say']" />
  <x-key :items="$d['modes']['key']" />
  @else
  <x-note>No movement recorded.</x-note>
  @endif
</x-module>
