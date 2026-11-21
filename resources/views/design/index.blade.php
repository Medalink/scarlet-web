@php($base = App\View\Design\Gallery::BASE)
<x-layouts.app title="Scarlet page gallery" :base="$base">
<div class="wrap">
  <x-page-head title="Page gallery" :single="true">
    <x-slot:sub>Each comp in docs/comps rebuilt from invented data through the layout and the components. Local only.</x-slot:sub>
  </x-page-head>
  <x-sheet>
    <x-module span="w5" :first="true" id="h-pages" heading="Pages">
      <x-rank :plain="true" :rows="array_map(fn (string $page): array => ['label' => $page, 'href' => $base.'/'.$page, 'value' => App\View\Design\Gallery::PAGES[$page], 'share' => 0], $pages)" />
    </x-module>
    <x-module span="w7" :last="true" id="h-days" heading="Play time by day">
      <x-day-heat id="heat" :spec="$days['spec']" />
      <x-heat-key low="0" high="over 4h" :zero="true" />
      <x-readout id="days-out" :line="$days['line']" />
    </x-module>
  </x-sheet>
</div>
</x-layouts.app>
