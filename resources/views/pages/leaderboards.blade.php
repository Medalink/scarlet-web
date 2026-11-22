<x-layouts.app title="Scarlet leaderboards" current="leaderboards" description="{{ $d['sub'] }}">
<div class="wrap">
  <x-page-head title="Leaderboards">
    <x-slot:sub>{{ $d['sub'] }}</x-slot:sub>
    <x-slot:filters><x-scope-filters :filters="$d['filters']" /></x-slot:filters>
  </x-page-head>
  <ol class="hall">
    @foreach ($d['boards'] as $board)
    <li>
      <x-icon :name="$board['icon']" />
      <a class="board" href="{{ $board['href'] }}">{{ $board['title'] }}</a>
      @if ($board['name'])
      <span class="lead" data-class="{{ $board['class'] }}"><i class="dotc" aria-hidden="true"></i><a href="/c/{{ $board['name'] }}">{{ $board['name'] }}</a></span>
      <span class="val">{{ $board['value'] }}<small>{{ $board['count'] }}</small></span>
      @else
      <span class="lead none">{{ $board['none'] }}</span>
      <span class="val none">–</span>
      @endif
    </li>
    @endforeach
  </ol>
</div>
</x-layouts.app>
