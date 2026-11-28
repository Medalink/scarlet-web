<x-layouts.sc :title="$d['title'].': WoW Forever leaderboard'" current="leaderboards" :description="$d['sub']">
<div class="wrap">
  <x-page-head :title="$d['title']">
    <x-slot:sub>{{ $d['sub'] }}</x-slot:sub>
    <x-slot:filters>
      <x-scope-switch :choice="$scope['choice']" :path="$path" :names="$scope['names']" :as="$scope['as']" :query="$scope['query']" />
      <x-pick label="Board" :options="$d['boards']" :selected="$d['boardSelected']" />
      @if (count($d['rulesets']) > 4)<x-pick label="Ruleset" :options="array_map(fn ($item) => empty($item['disabled']) ? [$item['label'], $item['href']] : [$item['label'], null, true], $d['rulesets'])" :selected="collect($d['rulesets'])->firstWhere('on', true)['label'] ?? 'All'" />@else<x-seg label="Ruleset" :items="$d['rulesets']" />@endif
      @if ($d['servers'])<x-seg label="Server" :items="$d['servers']" />@endif
      <x-pick label="Class" :options="$d['classes']" :selected="$d['classSelected']" />
    </x-slot:filters>
  </x-page-head>
  <x-places :groups="$places" heading="Your place on this board" />
  <h2 class="vh" id="h-board">{{ $d['title'] }}</h2>
  @if ($d['podium'])
  <ol class="podium">
    @foreach ($d['podium'] as $place)
    <li @class(['p'.$place['rank'], 'me' => $place['mine']])>
      <span class="place">{{ $place['rank'] }}@if ($place['rank'] === 1)<x-icon name="crown" />@endif</span>
      <a class="who" href="{{ $place['href'] }}" data-class="{{ $place['class'] }}">{{ $place['name'] }}</a>
      <span class="what" data-class="{{ $place['class'] }}"><i class="dotc" aria-hidden="true"></i>{{ $place['what'] }}@if ($place['tag'])<span class="tag hc">{{ $place['tag'] }}</span>@endif</span>
      <span class="val">{{ $place['value'] }}@if ((string) $place['unit'] !== '')<small>{{ $place['unit'] }}</small>@endif</span>
      <span class="plinth"></span>
    </li>
    @endforeach
  </ol>
  @endif
  <x-sheet>
    <x-module span="w12" :first="true" :last="true">
      @if ($d['rows'])
      <x-table :rows="$d['rows']" :columns="$d['columns']" style="font-size:1rem" />
      @endif
      @if ($d['podium'] || $d['rows'])
      <p class="note">Your character is highlighted when you are signed in. {{ $d['count'] }}@if ($d['next']) <a href="{{ $d['next'] }}">Next {{ $d['step'] }}</a>@endif</p>
      @else
      <x-note>No public character is on this board yet.</x-note>
      @endif
    </x-module>
  </x-sheet>
</div>
</x-layouts.sc>
