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
    <x-module span="w7" :first="true" id="h-s" heading="Your share of group loot">
      @if ($d['share']['empty'])
      <x-note>{{ $d['share']['empty'] }}</x-note>
      @else
      <x-table :rows="$d['share']['rows']" :columns="$d['share']['columns']" style="margin-top:1.1rem" />
      <p class="note">{{ $d['share']['note'] }}</p>
      @endif
    </x-module>
    <x-module span="w5" :last="true" id="h-z" heading="Kills an hour by group size">
      @if ($d['kills']['empty'])
      <x-note>{{ $d['kills']['empty'] }}</x-note>
      @else
      <x-table :rows="$d['kills']['rows']" :columns="$d['kills']['columns']" style="margin-top:1.1rem" />
      <p class="note">{{ $d['kills']['note'] }}</p>
      @endif
    </x-module>
    <x-module span="w12" :first="true" :last="true" id="h-r" heading="Rolls">
      @if ($d['rolls']['empty'])
      <x-note>{{ $d['rolls']['empty'] }}</x-note>
      @else
      <x-table :rows="$d['rolls']['rows']" :columns="$d['rolls']['columns']" style="margin-top:1.1rem" />
      @if ($d['rolls']['thresholds'])
      <x-table :rows="$d['rolls']['thresholds']" :columns="$d['rolls']['thresholdColumns']" style="margin-top:1.4rem" />
      @endif
      <p class="note">{{ $d['rolls']['note'] }}</p>
      @endif
    </x-module>
    <x-module span="w12" :first="true" :last="true" id="h-g" heading="Group composition">
      @if ($d['composition']['empty'])
      <x-note>{{ $d['composition']['empty'] }}</x-note>
      @else
      @if ($d['composition']['need'])
      <x-table :rows="$d['composition']['need']['rows']" :columns="$d['composition']['need']['columns']" style="margin-top:1.1rem" />
      @endif
      @if ($d['composition']['skins'])
      <x-table :rows="$d['composition']['skins']['rows']" :columns="$d['composition']['skins']['columns']" style="margin-top:1.4rem" />
      @endif
      @if ($d['composition']['roles'])
      <x-table :rows="$d['composition']['roles']['rows']" :columns="$d['composition']['roles']['columns']" style="margin-top:1.4rem" />
      @endif
      <p class="note">{{ $d['composition']['note'] }}</p>
      @endif
    </x-module>
    @if ($d['marks'])
    <x-module span="w12" :first="true" :last="true" id="h-m" heading="Marked differences">
      @if ($d['marks']['empty'])
      <x-note>{{ $d['marks']['empty'] }}</x-note>
      @else
      <x-table :rows="$d['marks']['rows']" :columns="$d['marks']['columns']" style="margin-top:1.1rem" />
      <p class="note">{{ $d['marks']['note'] }}</p>
      @endif
    </x-module>
    @endif
  </x-sheet>
</div>
</x-layouts.app>
