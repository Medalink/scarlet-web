<x-layouts.app title="Hardcore deaths" current="stats" description="Hardcore characters that died in WoW Forever, by the level they died at and by day." canonical="/hardcore-deaths">
<div class="wrap">
  <x-page-head title="Hardcore deaths">
    <x-slot:sub>{{ $d['sub'] }}</x-slot:sub>
  </x-page-head>
  <x-sheet>
    <x-module :first="true" :last="$d['days'] === null" id="h-levels" heading="Deaths by level">
      @if ($d['levels'])
      <x-plot id="levels" label="Hardcore deaths at each level" :spec="$d['levels']" />
      @else
      <x-note>No death with a level recorded yet.</x-note>
      @endif
    </x-module>
    <x-module :last="true" id="h-days" heading="Deaths by day">
      @if ($d['days'])
      <x-plot id="days" label="Hardcore deaths on each of the last {{ \App\View\Pages\HardcoreDeathsPage::DAYS }} days, in UTC" :spec="$d['days']" />
      @else
      <x-note>No hardcore deaths recorded yet.</x-note>
      @endif
    </x-module>
  </x-sheet>
</div>
</x-layouts.app>
