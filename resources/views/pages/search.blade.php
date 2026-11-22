<x-layouts.app :title="$d ? 'Scarlet search: '.$term : 'Search Scarlet'" current="search" description="Find a character, creature, item, zone or quest by name.">
<div class="wrap">
  <x-page-head title="Search" :single="true">
    <x-slot:body><x-search-box :value="$term" /></x-slot:body>
    <x-slot:sub>{{ $d['sub'] ?? ($term === '' ? 'Find a character, creature, item, zone or quest by name. Characters appear only when their owner made them public.' : 'Type at least two characters.') }}</x-slot:sub>
  </x-page-head>
  @if ($d && ! $d['empty'])
  <x-sheet>
    <x-module span="w12" :first="true" :last="true" style="padding-bottom:1rem"><x-seg label="Kind" :items="$d['kinds']" /></x-module>
    @if ($d['kind'])
    @php($group = $d['groups'][$d['kind']->value])
    <x-module span="w12" :first="true" :last="true">
      <x-search-group :title="$group['title']" :icon="$group['icon']" :count="$group['count']" :hits="$group['hits']" />
      @if ($d['next'])<p class="note"><a href="{{ $d['next'] }}">Next {{ \App\View\Pages\SearchPage::PAGE }}</a></p>@endif
    </x-module>
    @else
    @php($left = array_filter([$d['groups']['characters'], $d['groups']['zones']], fn ($g) => $g['hits']))
    @php($right = array_filter([$d['groups']['creatures'], $d['groups']['items'], $d['groups']['quests']], fn ($g) => $g['hits']))
    <x-module span="w5" :first="true">
      @foreach ($left as $i => $group)<x-search-group :title="$group['title']" :icon="$group['icon']" :count="$group['count']" :hits="$group['hits']" :more="$group['more']" :spaced="$loop->index > 0" />@endforeach
    </x-module>
    <x-module span="w7" :last="true">
      @foreach ($right as $i => $group)<x-search-group :title="$group['title']" :icon="$group['icon']" :count="$group['count']" :hits="$group['hits']" :more="$group['more']" :spaced="$loop->index > 0" />@endforeach
    </x-module>
    @endif
  </x-sheet>
  @endif
</div>
</x-layouts.app>
