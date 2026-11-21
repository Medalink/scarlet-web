<x-layouts.app :title="'Scarlet talents: '.$d['title']" current="talents" description="{{ $d['sub'] }}">
<div class="wrap">
  <x-page-head :title="$d['title']">
    <x-slot:sub>{{ $d['sub'] }}</x-slot:sub>
    <x-slot:filters>
      <x-scope-filters :filters="$filters" />
    </x-slot:filters>
  </x-page-head>
  <x-sheet>
    <x-module span="w12" :first="true" :last="true" id="h-pick" heading="Pick rate by node">
      <x-slot:controls><x-seg label="Level" :items="$d['brackets']" /></x-slot:controls>
      @if ($d['n'] === 0)
      <x-note>No character has a build at these levels yet.</x-note>
      @else
      @if ($d['tree'])
      <x-talent-tree :tree="$d['tree']" :label="'Pick rate of each talent of '.$d['title'].' among '.$d['n'].' characters'" />
      <x-heat-key low="Few took it" high="Most took it" />
      @endif
      <x-table :rows="$d['nodes']['rows']" :columns="$d['nodes']['columns']" style="margin-top:1rem" />
      @endif
    </x-module>
    <x-module span="w5" :first="true" id="h-order" heading="Most common order">
      @if ($d['order']['rows'])
      <x-table :rows="$d['order']['rows']" :columns="$d['order']['columns']" />
      @else
      <x-note>No talent picks seen live yet.</x-note>
      @endif
    </x-module>
    <x-module span="w7" :last="true" id="h-brackets" heading="Builds by level bracket">
      <x-table :rows="$d['builds']['rows']" :columns="$d['builds']['columns']" />
    </x-module>
  </x-sheet>
</div>
</x-layouts.app>
