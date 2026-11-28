<x-layouts.sc title="WoW Forever leaderboards" current="leaderboards" :description="$d['sub']">
<div class="wrap pb-20">
  <x-page-head :title="$d['title']">
    <x-slot:filters><x-scope-switch :choice="$scope['choice']" :path="$path" :names="$scope['names']" :as="$scope['as']" :query="$scope['query']" /><x-scope-filters :filters="$d['filters']" /></x-slot:filters>
  </x-page-head>
  <x-places :groups="$places" heading="Your places on the boards" />
  @php($names = array_values(array_filter(['Levelling', 'Combat', 'Travel', 'Professions', 'Feats'], fn ($name) => isset($d['groups'][$name]) || isset($d['quiet'][$name]))))
  <x-sc.section-nav class="mt-6" :sections="array_combine(array_map(fn ($name) => \Illuminate\Support\Str::slug($name), $names), $names)" />
  @foreach ($names as $name)
    <section class="sc-sec" id="{{ \Illuminate\Support\Str::slug($name) }}" style="margin-top:40px">
      <h2 class="sc-h" style="font-size:32px">{{ $name }}</h2>
      @if (isset($d['groups'][$name]))
      <ol class="hall mt-3">
        @foreach ($d['groups'][$name] as $board)
        <li>
          <x-icon :name="$board['icon']" />
          <a class="board" href="{{ $board['href'] }}">{{ $board['title'] }}</a>
          <span class="lead" data-class="{{ $board['class'] }}"><i class="dotc" aria-hidden="true"></i><a href="{{ $board['page'] }}">{{ $board['name'] }}</a></span>
          <span class="val">{{ $board['value'] }}<small>{{ $board['count'] }}</small></span>
        </li>
        @endforeach
      </ol>
      @endif
      @if (isset($d['quiet'][$name]))
      <p class="note">No public character yet: @foreach ($d['quiet'][$name] as $board)<a href="{{ $board['href'] }}">{{ $board['title'] }}</a>{{ $loop->last ? '' : ', ' }}@endforeach</p>
      @endif
    </section>
  @endforeach
</div>
</x-layouts.sc>
