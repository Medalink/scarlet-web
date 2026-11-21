<x-module :span="$span" :first="$first" :last="$last" id="h-gold">
  <h2 id="h-gold">Gold <span class="priv">{{ $d['owner'] ? 'Only you' : 'Shared by the owner' }}</span></h2>
  <p class="big"><span class="money">{{ $d['gold']['balance'] }}</span></p>
  <p class="sub">{{ $d['gold']['change'] }}</p>
  @if ($d['gold']['spec'])<x-plot id="gold" label="Balance at the end of each of the last sessions" :spec="$d['gold']['spec']" />@endif
</x-module>
