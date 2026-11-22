<x-layouts.app :title="$d ? 'Scarlet search: '.$term : 'Search Scarlet'" current="search" description="Find a character, creature, item, zone, quest or page by name.">
<section class="srch-head">
  <div class="wrap">
    <x-page-head title="Search" :single="true">
      <x-slot:body><x-search-box :value="$term" /></x-slot:body>
      <x-slot:sub>{{ $d['sub'] ?? ($term === '' ? 'Find a character, creature, item, zone, quest or page by name. Characters appear only when their owner made them public.' : 'Type at least two characters.') }}</x-slot:sub>
    </x-page-head>
  </div>
</section>
<div class="wrap">
  @if ($d && $d['close'])<p class="srch-close">{{ $d['close'] }}</p>@endif
  @if ($d && ! $d['empty'])
  <x-sheet>
    @if ($d['best'])
    <x-module span="w12" :first="true" :last="true" style="padding-bottom:1rem"><x-search-top :best="$d['best']" /></x-module>
    @endif
    <x-module span="w12" :first="true" :last="true" style="padding-bottom:1rem"><x-seg label="Kind" :items="$d['kinds']" /></x-module>
    @if ($d['kind'])
    @php($group = $d['groups'][$d['kind']->value])
    <x-module span="w12" :first="true" :last="true">
      <x-search-group :title="$group['title']" :icon="$group['icon']" :count="$group['count']" :hits="$group['hits']" />
      @if ($d['next'])<p class="note"><a href="{{ $d['next'] }}">Next {{ \App\View\Pages\SearchPage::PAGE }}</a></p>@endif
    </x-module>
    @else
    @php($left = array_filter([$d['groups']['characters'], $d['groups']['zones'], $d['groups']['pages'] ?? null], fn ($g) => $g && $g['hits']))
    @php($right = array_filter([$d['groups']['creatures'], $d['groups']['items'], $d['groups']['quests']], fn ($g) => $g['hits']))
    @if ($left)
    <x-module :span="$right ? 'w5' : 'w12'" :first="true" :last="! $right">
      @foreach ($left as $group)<x-search-group :title="$group['title']" :icon="$group['icon']" :count="$group['count']" :hits="$group['hits']" :more="$group['more']" :spaced="$loop->index > 0" />@endforeach
    </x-module>
    @endif
    @if ($right)
    <x-module :span="$left ? 'w7' : 'w12'" :first="! $left" :last="true">
      @foreach ($right as $group)<x-search-group :title="$group['title']" :icon="$group['icon']" :count="$group['count']" :hits="$group['hits']" :more="$group['more']" :spaced="$loop->index > 0" />@endforeach
    </x-module>
    @endif
    @endif
  </x-sheet>
  @endif
  @if ($start)<x-search-start :groups="$start" />@endif
</div>
</x-layouts.app>
