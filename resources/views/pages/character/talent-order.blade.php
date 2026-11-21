<x-module :span="$span" :first="$first" :last="$last" id="h-talent-order" heading="Talent order by level">
  @if ($d['talents']['order']['rows'])
  <x-table :rows="$d['talents']['order']['rows']" :columns="$d['talents']['order']['columns']" />
  @endif
  <p class="total">{{ $d['talents']['order']['total'] }}</p>
</x-module>
