<x-layouts.app title="Scarlet admin: characters" :index="false" :brand="false">
<div class="wrap">
  <x-page-head title="Characters">
    <x-slot:sub>Every character uploaded, public and private, the most recently seen first. Search part of a name, or #12 for the characters of account 12.</x-slot:sub>
    <x-slot:filters><x-seg label="Admin pages" :items="$d['nav']" /></x-slot:filters>
  </x-page-head>
  <form method="get" action="/admin/characters" role="search" style="margin-top:1.6rem;display:flex;gap:.8rem;flex-wrap:wrap;align-items:center">
    <label class="vh" for="admin-q">Character name or #account</label>
    <input id="admin-q" class="field" name="q" type="search" value="{{ $d['search'] }}" maxlength="64" placeholder="Name or #account" autocomplete="off" style="flex:1 1 14rem;min-width:0">
@if ($d['show'] !== '')
    <input type="hidden" name="show" value="{{ $d['show'] }}">
@endif
    <x-btn type="submit">Search</x-btn>
    <x-seg label="Visibility" :items="$d['filters']" />
  </form>
  <x-sheet>
    <x-module span="w12" :first="true" :last="true" id="h-characters" :heading="$d['page']">
@if ($d['rows'] === [])
      <x-note>{{ $d['empty'] }}</x-note>
@else
      <x-table style="margin-top:1.1rem" :columns="[['label' => 'Character'], ['label' => 'Visibility'], ['label' => 'Level', 'numeric' => true], ['label' => 'Race and class'], ['label' => 'Realm'], ['label' => 'Account'], ['label' => 'Installs', 'numeric' => true], ['label' => 'Sessions', 'numeric' => true], ['label' => 'Played', 'numeric' => true], ['label' => 'Last seen', 'numeric' => true, 'local' => 'Last seen'], ['label' => 'First seen', 'numeric' => true, 'local' => 'First seen']]" :rows="$d['rows']" />
@endif
@if ($d['previous'] || $d['next'])
      <div style="margin-top:1.2rem;display:flex;gap:.8rem">
@if ($d['previous'])<x-btn kind="quiet" :href="$d['previous']">Previous page</x-btn>@endif
@if ($d['next'])<x-btn kind="quiet" :href="$d['next']">Next page</x-btn>@endif
      </div>
@endif
    </x-module>
  </x-sheet>
</div>
</x-layouts.app>
