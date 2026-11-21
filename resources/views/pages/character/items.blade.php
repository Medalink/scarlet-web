<x-module :span="$span" :first="$first" :last="$last" id="h-items" :heading="'Items found'.$d['since']">
  @if ($d['items'])
  @if ($d['items']['looted'])
  <p class="sub">{{ $d['items']['looted']['total'] }}</p>
  <x-stack-bar :segments="$d['items']['looted']['segments']" :label="$d['items']['looted']['say']" />
  <x-key :items="$d['items']['looted']['key']" />
  @endif
  @if ($d['items']['best'])
  <p @class(['sub', 'gap' => $d['items']['looted']])>Best items received</p>
  <x-rank :rows="$d['items']['best']" />
  @endif
  @else
  <x-note>No items recorded.</x-note>
  @endif
</x-module>
