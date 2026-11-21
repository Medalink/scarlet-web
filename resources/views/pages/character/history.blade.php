<x-module :span="$span" :first="$first" :last="$last" id="h-hist" heading="Play history">
  @if ($d['sessions']['rows'])
  <x-table :rows="$d['sessions']['rows']" :columns="$d['sessions']['columns']" />
  @if ($d['more'])<p class="note"><a href="{{ $d['more'] }}">Show more sessions</a></p>@endif
  @else
  <x-note>No sessions recorded yet.</x-note>
  @endif
</x-module>
