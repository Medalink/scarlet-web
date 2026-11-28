{{-- A drop rate on a 0 to 100% track. estimate: App\Support\RateEstimate. The band runs from the low to the high end of the 95% range and the dot marks the rate; 10 to 29 windows draw hollow; under 10 windows there is no rate to draw, only a dash, and the counts are the page's `k of n` column. `counts` adds the counts after a stated rate and shows them in place of the dash. --}}
@props(['estimate', 'counts' => false])
@if ($estimate->stated())
<x-rate-mark :estimate="$estimate" />{{ $estimate->percent() }}@if ($counts)<span class="mut"> {{ $estimate->counts() }}</span>@endif
@elseif ($counts)
<span class="mut">{{ $estimate->counts() }}</span>
@else
<span class="mut">–</span>
@endif
