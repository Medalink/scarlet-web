<x-layouts.sc title="WoW Forever leaderboards" current="leaderboards" :description="$d['sub']">
<div class="wrap pb-20">
  <x-page-head :title="$d['title']">
    <x-slot:sub>{{ $d['sub'] }}</x-slot:sub>
    <x-slot:filters><x-scope-switch :choice="$scope['choice']" :path="$path" :names="$scope['names']" :as="$scope['as']" :query="$scope['query']" /><x-scope-filters :filters="$d['filters']" /></x-slot:filters>
  </x-page-head>
  <x-places :groups="$places" heading="Your places on the boards" />
  <x-sc.section-nav class="mt-6" :sections="array_combine(array_map(fn ($name) => \Illuminate\Support\Str::slug($name), array_keys($d['groups'])), array_keys($d['groups']))" />
  @foreach (['Levelling', 'Combat', 'Travel', 'Professions', 'Feats'] as $name)
    @continue (! isset($d['groups'][$name]))
    <section class="sc-sec" id="{{ \Illuminate\Support\Str::slug($name) }}" style="margin-top:40px">
      <h2 class="sc-h" style="font-size:32px">{{ $name }}</h2>
      <ol class="hall mt-3">
        @foreach ($d['groups'][$name] as $board)
        <li>
          <x-icon :name="$board['icon']" />
          <a class="board" href="{{ $board['href'] }}">{{ $board['title'] }}</a>
          @if ($board['name'])
          <span class="lead" data-class="{{ $board['class'] }}"><i class="dotc" aria-hidden="true"></i><a href="{{ $board['page'] }}">{{ $board['name'] }}</a></span>
          <span class="val">{{ $board['value'] }}<small>{{ $board['count'] }}</small></span>
          @else
          <span class="lead none">{{ $board['none'] }}</span>
          <span class="val none">–</span>
          @endif
        </li>
        @endforeach
      </ol>
    </section>
  @endforeach
</div>
</x-layouts.sc>
