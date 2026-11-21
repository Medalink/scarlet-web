<x-module :span="$span" :first="$first" :last="$last" id="h-killed" heading="Most killed creatures">
  @if ($d['killed'])
  <x-rank :rows="$d['killed']" />
  @else
  <x-note>No named creatures killed yet.</x-note>
  @endif
</x-module>
