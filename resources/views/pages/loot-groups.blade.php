<x-layouts.sc title="Loot rules and group size: what they change in loot" description="What loot rules and group size change in what a corpse holds, how much of it the looter keeps and how fast a group kills in WoW Forever, with the counts behind every figure." current="loot">
<div class="wrap">
  <x-page-head title="Loot rules and group size" :single="true">
    <x-slot:sub>@if ($d['source'])<a href="{{ $d['source']['href'] }}">{{ $d['source']['name'] }}</a> only. @endif{{ $d['sub'] }}@if ($d['source']) <a href="/loot?view=groups">All creatures</a>@endif</x-slot:sub>
    <x-slot:filters><x-seg label="Show" :items="$d['tabs']" /></x-slot:filters>
  </x-page-head>
  @if ($d['figures'])<x-figures :items="$d['figures']" />@endif
  @php
    $has = fn (array $block): bool => ! $block['empty'];
    $held = ! $d['none'] && $has($d['effects']);
    $kept = $has($d['kept']);
    $share = ! $d['none'] && $has($d['share']);
    $kills = $has($d['kills']);
    $rolls = ! $d['none'] && $has($d['rolls']);
    $comp = $has($d['composition']);
    $marks = $d['marks'] && ! $d['marks']['empty'];
  @endphp
  <x-sheet>
    @if ($d['none'])
    <x-module span="w12" :first="true" :last="true" id="h-n" heading="Loot rules">
      <x-note>No loot rule is recorded yet.</x-note>
    </x-module>
    @endif
    @if ($held || $kept)
    @if ($held)
    <x-module :span="$kept ? 'w5' : 'w12'" :first="true" :last="! $kept" id="h-h" heading="What a corpse held, by loot rule">
      <x-table :rows="$d['effects']['rows']" :columns="$d['effects']['columns']" style="margin-top:1.1rem" />
      <p class="note">{{ $d['effects']['note'] }}</p>
    </x-module>
    @endif
    @if ($kept)
    <x-module :span="$held ? 'w7' : 'w12'" :first="! $held" :last="true" id="h-k" heading="What the looter kept">
      <x-table :rows="$d['kept']['rows']" :columns="$d['kept']['columns']" style="margin-top:1.1rem" />
    </x-module>
    @endif
    @endif
    @if ($share || $kills)
    @if ($share)
    <x-module :span="$kills ? 'w7' : 'w12'" :first="true" :last="! $kills" id="h-s" :heading="$d['share']['heading']">
      <x-table :rows="$d['share']['rows']" :columns="$d['share']['columns']" style="margin-top:1.1rem" />
    </x-module>
    @endif
    @if ($kills)
    <x-module :span="$share ? 'w5' : 'w12'" :first="! $share" :last="true" id="h-z" :heading="$d['kills']['heading']">
      <x-table :rows="$d['kills']['rows']" :columns="$d['kills']['columns']" style="margin-top:1.1rem" />
    </x-module>
    @endif
    @endif
    @if ($rolls)
    <x-module span="w12" :first="true" :last="true" id="h-r" :heading="$d['rolls']['heading']">
      <x-table :rows="$d['rolls']['rows']" :columns="$d['rolls']['columns']" style="margin-top:1.1rem" />
      @if ($d['rolls']['thresholds'])
      <x-table :rows="$d['rolls']['thresholds']" :columns="$d['rolls']['thresholdColumns']" style="margin-top:1.4rem" />
      @endif
    </x-module>
    @endif
    @if ($comp)
    <x-module span="w12" :first="true" :last="true" id="h-g" :heading="$d['composition']['heading']">
      @if ($d['composition']['need'])
      <x-table :rows="$d['composition']['need']['rows']" :columns="$d['composition']['need']['columns']" style="margin-top:1.1rem" />
      @endif
      @if ($d['composition']['skins'])
      <x-table :rows="$d['composition']['skins']['rows']" :columns="$d['composition']['skins']['columns']" style="margin-top:1.4rem" />
      @endif
      @if ($d['composition']['roles'])
      <x-table :rows="$d['composition']['roles']['rows']" :columns="$d['composition']['roles']['columns']" style="margin-top:1.4rem" />
      @endif
    </x-module>
    @endif
    @if ($marks)
    <x-module span="w12" :first="true" :last="true" id="h-m" heading="Differences that may be counting faults">
      <x-table :rows="$d['marks']['rows']" :columns="$d['marks']['columns']" style="margin-top:1.1rem" />
    </x-module>
    @endif
  </x-sheet>
</div>
</x-layouts.sc>
