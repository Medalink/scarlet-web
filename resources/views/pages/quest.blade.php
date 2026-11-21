<x-layouts.app :title="'Scarlet quest: '.$d['name']" description="Turn-ins, abandons and the levels and zones where {{ $d['name'] }} is turned in.">
<div class="wrap">
  <x-page-head :title="$d['name']">
    <x-slot:sub>{{ $d['sub'] }}</x-slot:sub>
    <x-slot:filters><x-scope-filters :filters="$d['filters']" /></x-slot:filters>
  </x-page-head>
  <x-figures :items="$d['figures']" />
  <x-sheet>
    <x-module span="w7" :first="true" id="h-l" heading="Turn-ins by the character's level">
      @if ($d['byLevel'])
      <x-plot id="by-level" label="Turn-ins of this quest at each character level" :spec="$d['byLevel']" />
      @else
      <x-note>No turn-ins recorded yet.</x-note>
      @endif
    </x-module>
    <x-module span="w5" :last="true" id="h-z" heading="Where it is turned in">
      @if ($d['zones'])
      <x-rank :rows="$d['zones']" plain />
      @else
      <x-note>No zone recorded yet.</x-note>
      @endif
    </x-module>
  </x-sheet>
</div>
</x-layouts.app>
