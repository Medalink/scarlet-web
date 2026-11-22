<x-module :span="$span" :first="$first" :last="$last" :level="$level" id="h-gold">
  <h{{ $level }} id="h-gold">Gold <span class="priv">{{ $d['owner'] ? 'Only you' : 'Shared by the owner' }}</span></h{{ $level }}>
  <p class="big"><span class="money">{{ $d['gold']['balance'] }}</span></p>
  <p class="sub">{{ $d['gold']['change'] }}</p>
  @if ($d['gold']['spec'])<x-plot id="gold" label="Balance at the end of each recent session." :spec="$d['gold']['spec']" />@endif
</x-module>
