<x-module :span="$span" :first="$first" :last="$last" :level="$level" id="h-respecs" heading="Respecs">
  @if ($d['talents']['respecs']['rows'])
  <x-table :rows="$d['talents']['respecs']['rows']" :columns="$d['talents']['respecs']['columns']" />
  @else
  <x-note>No respec recorded.</x-note>
  @endif
</x-module>
