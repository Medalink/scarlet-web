<x-module :span="$span" :first="$first" :last="$last" :level="$level" id="h-items" :heading="'Items found'.$d['since']">
  @if ($d['items'])
  @if ($d['items']['looted'])
  <p class="sub">Items looted</p>
  <x-stack-bar :segments="$d['items']['looted']['segments']" :label="$d['items']['looted']['say']" />
  <x-key :items="$d['items']['looted']['key']" />
  @endif
  @if ($d['items']['best'])
  <p @class(['sub', 'gap' => $d['items']['looted']])>Best items received</p>
  <ol class="best">@foreach ($d['items']['best'] as $row)<li><b @class(['lvl', $row['quality']])>{{ $row['level'] }}</b><a @class(['who', $row['quality']]) href="{{ $row['href'] }}">{{ $row['label'] }}</a><span class="val">{{ $row['value'] }}</span></li>@endforeach</ol>
  @endif
  @else
  <x-note>No items recorded.</x-note>
  @endif
</x-module>
