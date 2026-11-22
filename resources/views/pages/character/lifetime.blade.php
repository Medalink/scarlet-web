<x-module :span="$span" :first="$first" :last="$last" :level="$level" id="h-lifetime" heading="Lifetime">
  @if ($d['lifetime']['figures'])<x-figures :items="$d['lifetime']['figures']" />@endif
  @if ($d['lifetime']['locks'])
  <h3 class="chart-name">Instance locks</h3>
  <x-table :columns="$d['lifetime']['locks']['columns']" :rows="$d['lifetime']['locks']['rows']" />
  @endif
</x-module>
