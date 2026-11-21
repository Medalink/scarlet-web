<x-module :span="$span" :first="$first" :last="$last" id="h-deaths" heading="Deaths by level">
  @if ($d['deaths']['strip'])
  <x-level-strip :cells="$d['deaths']['strip']" label="Deaths at each level" />
  <x-table :rows="$d['deaths']['rows']" :columns="$d['deaths']['columns']" style="margin-top:1.4rem" />
  @endif
  <p class="total">{{ $d['deaths']['total'] }}</p>
</x-module>
