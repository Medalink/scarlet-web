<x-layouts.app :title="'Scarlet item: '.$d['name']" description="Where {{ $d['name'] }} drops and how often.">
<div class="wrap">
  <x-page-head :title="$d['name']" :class="$d['quality']" :single="true">
    <x-slot:sub>{{ $d['sub'] }}@if ($d['sell']) Sells for {{ $d['sell'] }}.@endif</x-slot:sub>
  </x-page-head>
  <x-figures :items="$d['figures']" />
  <x-sheet>
    <x-module span="w12" :first="true" :last="true" id="h-s" heading="Where it drops">
      @if ($d['rows'])
      <x-table :rows="$d['rows']" :columns="[['label' => 'Source'], ['label' => 'Loot rule'], ['label' => 'Drop rate', 'numeric' => true], ['label' => 'Looted', 'numeric' => true], ['label' => 'Dropped', 'numeric' => true]]" style="margin-top:1.1rem" />
      <p class="note">A drop rate is drops over corpses looted for the first time under that loot rule. Drop rates cover every ruleset.</p>
      @else
      <x-note>No drops of this item are recorded yet.</x-note>
      @endif
    </x-module>
  </x-sheet>
</div>
</x-layouts.app>
