<x-module :span="$span" :first="$first" :last="$last" :level="$level" id="h-runs" :heading="'Dungeon runs'.($d['runs']['fromAddons'] ?? false ? '' : $d['since'])">
  @if ($d['runs'])
  @if ($d['runs']['rows'])<x-run-list :rows="$d['runs']['rows']" />@endif
  <p class="total">{{ $d['runs']['total'] }}</p>
  @else
  <x-note>No dungeon runs recorded.</x-note>
  @endif
</x-module>
