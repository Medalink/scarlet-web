<x-module :span="$span" :first="$first" :last="$last" id="h-items" heading="Items found">
  @if ($d['items'])
  <x-stack-bar :segments="$d['items']['segments']" :label="$d['items']['say']" />
  <x-key :items="$d['items']['key']" />
  @if ($d['items']['best'])<x-rank :rows="$d['items']['best']" />@endif
  <p class="total">{{ $d['items']['total'] }}</p>
  @else
  <x-note>No looted items recorded.</x-note>
  @endif
</x-module>
