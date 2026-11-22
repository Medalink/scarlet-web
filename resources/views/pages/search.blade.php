<x-layouts.app :title="$d ? 'Search: '.$term : 'Search characters, creatures, items, zones, quests and pages'" current="search" description="Find a character, creature, item, zone, quest or page by name." :index="! $d">
<section class="srch-head">
  <div class="wrap">
    <x-page-head title="Search" :single="true">
      <x-slot:body><x-search-box :value="$term" /></x-slot:body>
      <x-slot:sub>{{ $d['sub'] ?? ($term === '' ? 'Find a character, creature, item, zone, quest or page by name. Characters appear only when their owner made them public.' : 'Type at least two letters or digits.') }}</x-slot:sub>
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
      @if ($d['prev'] || $d['next'])<p class="pager">@if ($d['prev'])<a href="{{ $d['prev'] }}">Previous {{ \App\View\Pages\SearchPage::PAGE }}</a>@endif @if ($d['next'])<a href="{{ $d['next'] }}">Next {{ \App\View\Pages\SearchPage::PAGE }}</a>@endif</p>@endif
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
