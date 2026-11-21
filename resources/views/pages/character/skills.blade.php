<x-module :span="$span" :first="$first" :last="$last" id="h-skills" heading="Skills">
  @if ($d['skills'])
  <x-rank :rows="$d['skills']" />
  @else
  <x-note>No skills recorded.</x-note>
  @endif
</x-module>
