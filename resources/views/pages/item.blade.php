<x-layouts.app :title="$d['name'].': where it drops and how often'" :description="trim($d['sub'].' Where '.$d['name'].' drops in WoW Forever and how often.')">
<div class="wrap">
  <x-page-head :title="$d['name']" :class="$d['quality']" :single="true">
    <x-slot:sub>{{ $d['sub'] }}@if ($d['sell']) Sells for {{ $d['sell'] }}.@endif</x-slot:sub>
  </x-page-head>
  <x-figures :items="$d['figures']" />
  <x-sheet>
    <x-module span="w12" :first="true" :last="! $d['prices']" id="h-s" heading="Where it drops">
      @if ($d['rows'])
      <x-table :rows="$d['rows']" :columns="[['label' => 'Source'], ['label' => 'Loot rule'], ['label' => 'Drop rate', 'numeric' => true], ['label' => 'Looted', 'numeric' => true], ['label' => 'Dropped', 'numeric' => true]]" style="margin-top:1.1rem" />
      <p class="note">A drop rate is drops over corpses looted for the first time under that loot rule. Drop rates cover every ruleset.</p>
      @else
      <x-note>No drops of this item are recorded yet.</x-note>
      @endif
    </x-module>
    @if ($d['prices'])
    <x-module span="w12" :last="true" id="h-p" heading="Auction price">
      @foreach ($d['prices'] as $market)
      <h3 id="{{ $market['id'] }}-h">{{ $market['name'] }}</h3>
      @if ($market['spec'])
      <x-plot :id="$market['id']" label="Lowest auction price of {{ $d['name'] }} on each day in {{ $market['name'] }}" :spec="$market['spec']" />
      @endif
      <x-table :rows="$market['rows']" :columns="[['label' => 'Day'], ['label' => 'Lowest price', 'numeric' => true], ['label' => 'Seen in']]" style="margin-top:1.1rem" />
      @endforeach
      <p class="note">The lowest price a player's auction house showed that day, from searches. A browse row stands in on a day with no search. Each ruleset and realm is its own market.</p>
    </x-module>
    @endif
  </x-sheet>
</div>
</x-layouts.app>
