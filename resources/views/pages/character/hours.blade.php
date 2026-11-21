<x-module :span="$span" :first="$first" :last="$last" :level="$level" id="h-hours" heading="Play time by day and hour (UTC)">
  @if ($d['play'])
  <x-play-band :play="$d['play']" />
  @else
  <x-note>No play time recorded yet.</x-note>
  @endif
</x-module>
