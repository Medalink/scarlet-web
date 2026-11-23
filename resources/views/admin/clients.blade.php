<x-layouts.app title="Scarlet admin: clients" :index="false" :brand="false">
<div class="wrap">
  <x-page-head title="Clients">
    <x-slot:sub>{{ $d['sub'] }}</x-slot:sub>
    <x-slot:filters><x-seg label="Admin pages" :items="$d['nav']" /></x-slot:filters>
  </x-page-head>
  <x-figures :items="$d['figures']" />
  <x-sheet>
    <x-module span="w7" :first="true" id="h-builds" heading="App builds in use">
@if ($d['versions'] === [])
      <x-note>No install has been seen in the last 30 days.</x-note>
@else
      <x-rank :rows="$d['versions']" />
@endif
    </x-module>
    <x-module span="w5" :last="true" id="h-systems" heading="Operating systems">
@if ($d['systems'] === [])
      <x-note>No install has been seen in the last 30 days.</x-note>
@else
      <x-rank :rows="$d['systems']" :plain="true" />
@endif
    </x-module>

    <x-module span="w12" :first="true" :last="true" id="h-addons" heading="Addon versions of the last 30 days' sessions">
@if ($d['addons'] === [])
      <x-note>No session was decoded in the last 30 days.</x-note>
@else
      <x-rank :rows="$d['addons']" />
@endif
    </x-module>

    <x-module span="w12" :first="true" :last="true" id="h-recent" heading="Installs seen most recently">
@if ($d['recent'] === [])
      <x-note>No app has registered yet.</x-note>
@else
      <x-table style="margin-top:1.1rem" :columns="[['label' => 'Install'], ['label' => 'Name'], ['label' => 'Account'], ['label' => 'Client'], ['label' => 'Characters', 'numeric' => true], ['label' => 'Last seen', 'numeric' => true, 'local' => 'Last seen'], ['label' => 'Registered', 'numeric' => true, 'local' => 'Registered']]" :rows="$d['recent']" />
@endif
    </x-module>
  </x-sheet>
</div>
</x-layouts.app>
