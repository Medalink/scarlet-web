<x-layouts.app title="WoW Forever professions" current="professions" :description="$d['sub']">
<div class="wrap">
  <x-page-head :title="$d['title']">
    <x-slot:sub>{{ $d['sub'] }}</x-slot:sub>
    <x-slot:filters><x-scope-filters :filters="$filters" /></x-slot:filters>
  </x-page-head>
  <x-sheet>
    <x-module span="w12" :first="true" :last="true">
      <h2 class="vh" id="h-professions">Professions</h2>
      <x-table :rows="$d['rows']" :columns="$d['columns']" style="font-size:1rem" />
    </x-module>
  </x-sheet>
</div>
</x-layouts.app>
