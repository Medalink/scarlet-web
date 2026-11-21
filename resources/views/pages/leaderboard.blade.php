<x-layouts.app :title="'Scarlet leaderboards: '.strtolower($d['title'])" current="leaderboards" description="{{ $d['sub'] }}">
<div class="wrap">
  <x-page-head :title="$d['title']">
    <x-slot:sub>{{ $d['sub'] }}</x-slot:sub>
    <x-slot:filters>
      <x-pick label="Board" :options="$d['boards']" :selected="$d['boardSelected']" />
      @if (count($d['rulesets']) > 4)<x-pick label="Ruleset" :options="array_map(fn ($item) => [$item['label'], $item['href']], $d['rulesets'])" :selected="collect($d['rulesets'])->firstWhere('on', true)['label'] ?? 'All'" />@else<x-seg label="Ruleset" :items="$d['rulesets']" />@endif
      @if ($d['servers'])<x-seg label="Server" :items="$d['servers']" />@endif
      <x-pick label="Class" :options="$d['classes']" :selected="$d['classSelected']" />
    </x-slot:filters>
  </x-page-head>
  <x-sheet>
    <x-module span="w12" :first="true" :last="true">
      <h2 class="vh" id="h-board">{{ $d['title'] }}</h2>
      @if ($d['rows'])
      <x-table :rows="$d['rows']" :columns="$d['columns']" style="font-size:1rem" />
      <p class="note">Your character is highlighted when you are signed in. {{ $d['count'] }}@if ($d['next']) <a href="{{ $d['next'] }}">Next {{ $d['step'] }}</a>@endif</p>
      @else
      <x-note>No public character is on this board yet.</x-note>
      @endif
    </x-module>
  </x-sheet>
</div>
</x-layouts.app>
