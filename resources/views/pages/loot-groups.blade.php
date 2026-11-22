<x-layouts.app title="Loot rules and group size: what they change in loot" description="What loot rules and group size change in what a corpse holds, how much of it the looter keeps and how fast a group kills in WoW Forever, with the counts behind every figure." current="loot">
<div class="wrap">
  <x-page-head title="Loot rules and group size" :single="true">
    <x-slot:sub>@if ($d['source'])<a href="{{ $d['source']['href'] }}">{{ $d['source']['name'] }}</a> only. @endif{{ $d['sub'] }}@if ($d['source']) <a href="/loot/groups">All creatures</a>@endif</x-slot:sub>
  </x-page-head>
  <x-figures :items="$d['figures']" />
  <x-sheet>
    <x-module span="w5" :first="true" id="h-h" heading="What a corpse held, by loot rule">
      @if ($d['effects']['empty'])
      <x-note>{{ $d['effects']['empty'] }}</x-note>
      @else
      <x-table :rows="$d['effects']['rows']" :columns="$d['effects']['columns']" style="margin-top:1.1rem" />
      <p class="note">{{ $d['effects']['note'] }}</p>
      @endif
    </x-module>
    <x-module span="w7" :last="true" id="h-k" heading="What the looter kept">
      @if ($d['kept']['empty'])
      <x-note>{{ $d['kept']['empty'] }}</x-note>
      @else
      <x-table :rows="$d['kept']['rows']" :columns="$d['kept']['columns']" style="margin-top:1.1rem" />
      <p class="note">Items the uploader received from a corpse over the items the corpse held, by the rule and size in force when it was looted. Under 100% means other members took items.</p>
      @endif
    </x-module>
  </x-sheet>
</div>
</x-layouts.app>
