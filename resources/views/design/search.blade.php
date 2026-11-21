@php($base = App\View\Design\Gallery::BASE)
<x-layouts.app title="Search Scarlet" current="search" :base="$base">
<div class="wrap">
  <x-page-head title="Search" :single="true">
    <x-slot:body><x-search-box value="marsh" :action="$base.'/search'" /></x-slot:body>
    <x-slot:sub>23 results for "marsh". Characters appear only when their owner made them public.</x-slot:sub>
  </x-page-head>
  <x-sheet>
    <x-module span="w12" :first="true" :last="true" style="padding-bottom:1rem">
      <x-seg label="Kind" :items="array_map(fn (array $k): array => ['label' => $k[0], 'on' => isset($k[1])], $d['kinds'])" />
    </x-module>
    <x-module span="w5" :first="true">
      <x-search-group title="Characters" :count="2" :hits="$d['characters']" />
      <x-search-group title="Zones" :count="2" :hits="$d['zones']" :spaced="true" />
    </x-module>
    <x-module span="w7" :last="true">
      <x-search-group title="Creatures" :count="9" :hits="$d['creatures']" :more="['href' => '#', 'label' => '6 more creatures']" />
      <x-search-group title="Items" :count="6" :hits="$d['items']" :spaced="true" />
      <x-search-group title="Quests" :count="4" :hits="$d['quests']" :spaced="true" />
    </x-module>
  </x-sheet>
</div>
</x-layouts.app>
