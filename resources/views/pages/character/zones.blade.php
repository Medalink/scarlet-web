<x-module :span="$span" :first="$first" :last="$last" :level="$level" id="h-zones" :heading="'Hours by zone'.$d['since']">
  @if ($d['zoneHours']['rows'])
  <div @class(['cols' => $span === 'w12'])><x-rank :rows="$d['zoneHours']['rows']" /></div>
  @if ($d['zoneHours']['others'])<p class="total">{{ $d['zoneHours']['others'] }}</p>@endif
  @else
  <x-note>No play time recorded yet.</x-note>
  @endif
</x-module>
