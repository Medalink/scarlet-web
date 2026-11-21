<x-module :span="$span" :first="$first" :last="$last" :level="$level" id="h-deaths" heading="Deaths by level">
  @if ($d['deaths']['spec'])
  <x-plot id="deaths" label="Every death of {{ $d['name'] }} by level, one mark each, larger for a bigger killing blow." :spec="$d['deaths']['spec']" />
  @if ($d['deaths']['key'])<x-key :items="$d['deaths']['key']" />@endif
  @endif
  <p class="total">{{ $d['deaths']['total'] }}</p>
  @if ($d['deaths']['rows'])
  <details class="all">
    <summary>{{ $d['deaths']['all'] }}</summary>
    <x-table :rows="$d['deaths']['rows']" :columns="$d['deaths']['columns']" />
  </details>
  @endif
</x-module>
