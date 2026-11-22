@php($tail = array_keys(array_filter(['gathered' => $d['gathered'], 'bosses' => $d['bosses'], 'made' => $d['made'], 'uses' => $d['uses'], 'prices' => $d['prices']])))
@php($recipeColumns = [['label' => 'Recipe'], ['label' => 'Makes'], ['label' => 'Needs']])
<x-layouts.app :title="$d['name'].': where it drops and how often'" :description="trim($d['sub'].' Where '.$d['name'].' drops in WoW Forever and how often.')">
<div class="wrap">
  <x-page-head :title="$d['name']" :class="$d['quality']" :single="true">
    <x-slot:sub>{{ $d['sub'] }}@if ($d['sell']) Sells for {{ $d['sell'] }}.@endif</x-slot:sub>
  </x-page-head>
  <x-figures :items="$d['figures']" :five="true" />
  <x-sheet>
    <x-module span="w12" :first="true" :last="$tail === []" id="h-s" heading="Where it drops">
      @if ($d['rules'])
      <x-slot:controls><x-seg label="Loot rule" :items="$d['rules']" /></x-slot:controls>
      @endif
      @if ($d['rows'])
      <x-table :rows="$d['rows']" :columns="[['label' => 'Source'], ['label' => 'How'], ['label' => 'Drop rate', 'numeric' => true], ['label' => 'Range', 'numeric' => true], ['label' => 'Corpses', 'numeric' => true], ['label' => 'Per drop', 'numeric' => true]]" style="margin-top:1.1rem" />
      <p class="note">A drop rate is corpses of the source that held the item over corpses of the source read the same way for the first time, one row for each source and way it was read. Sources read 10 times or more rank by the low end of the 95% range; below the line, sources read fewer times show their counts only. Drop rates cover every ruleset.</p>
      @else
      <x-note>No drops of this item are recorded yet.</x-note>
      @endif
      <p class="note"><a href="/loot">All loot</a></p>
    </x-module>
    @if ($d['gathered'])
    <x-module span="w12" :last="end($tail) === 'gathered'" id="h-g" heading="Where it's gathered">
      <x-table :rows="$d['gathered']" :columns="[['label' => 'Source'], ['label' => 'Profession'], ['label' => 'Gathered', 'numeric' => true], ['label' => 'Share', 'numeric' => true], ['label' => 'Median skill', 'numeric' => true]]" style="margin-top:1.1rem" />
      <p class="note">@if ($d['gathered_shared'])Share is the skins of that creature that gave this item. @endif Gathering covers every ruleset.</p>
    </x-module>
    @endif
    @include('pages.item.loot', ['loot' => $loot])
    @if ($d['bosses'])
    <x-module span="w12" :last="end($tail) === 'bosses'" id="h-b" heading="Boss encounters that dropped it">
      <x-table :rows="$d['bosses']" :columns="[['label' => 'Encounter'], ['label' => 'Dropped', 'numeric' => true]]" style="margin-top:1.1rem" />
      <p class="note">A drop is counted once however many players of the group uploaded it.</p>
    </x-module>
    @endif
    @if ($d['made'])
    <x-module span="w12" :last="end($tail) === 'made'" id="h-m" heading="Recipes that make it">
      <x-table :rows="$d['made']" :columns="$recipeColumns" style="margin-top:1.1rem" />
    </x-module>
    @endif
    @if ($d['uses'])
    <x-module span="w12" :last="end($tail) === 'uses'" id="h-u" heading="Recipes that use it">
      <x-table :rows="$d['uses']" :columns="$recipeColumns" style="margin-top:1.1rem" />
    </x-module>
    @endif
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
