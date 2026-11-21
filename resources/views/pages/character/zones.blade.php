<x-module :span="$span" :first="$first" :last="$last" id="h-zones" :heading="'Hours by zone'.$d['since']">
  @if ($d['zoneHours']['rows'])
  <x-rank :rows="$d['zoneHours']['rows']" />
  @if ($d['zoneHours']['others'])<p class="total">{{ $d['zoneHours']['others'] }}</p>@endif
  @else
  <x-note>No play time recorded yet.</x-note>
  @endif
</x-module>
