<x-module :span="$span" :first="$first" :last="$last" :level="$level" id="h-distance" :heading="'Distance by how you moved'.$d['since']">
  @if ($d['modes'])
  <x-stack-bar :segments="$d['modes']['segments']" :label="$d['modes']['say']" />
  <x-key :items="$d['modes']['key']" />
  @else
  <x-note>No movement recorded.</x-note>
  @endif
</x-module>
