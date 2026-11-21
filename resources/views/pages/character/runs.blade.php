<x-module :span="$span" :first="$first" :last="$last" :level="$level" id="h-runs" :heading="'Dungeon runs'.$d['since']">
  @if ($d['runs'])
  <x-run-list :rows="$d['runs']['rows']" />
  <p class="total">{{ $d['runs']['total'] }}</p>
  @else
  <x-note>No dungeon runs recorded.</x-note>
  @endif
</x-module>
