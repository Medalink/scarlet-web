<x-module :span="$span" :first="$first" :last="$last" id="h-crafted" :heading="'Most crafted'.$d['since']">
  @if ($d['crafted'])
  <x-rank :rows="$d['crafted']" />
  @else
  <x-note>No crafts recorded.</x-note>
  @endif
</x-module>
