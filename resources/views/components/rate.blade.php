{{-- A drop rate on a 0 to 100% track. estimate: App\Support\RateEstimate. The band runs from the low to the high end of the 95% range and the dot marks the rate; 10 to 29 windows draw hollow; under 10 windows there is no rate to draw, only a dash, and the counts are the page's `k of n` column. --}}
@props(['estimate'])
@if ($estimate->stated())
<span @class(['rb', 'hollow' => $estimate->sample === \App\Support\RateSample::Hollow]) role="img" aria-label="{{ $estimate->percent() }}, range {{ $estimate->range() }} percent"><i class="rng" style="left:{{ round($estimate->low * 100, 1) }}%;width:{{ round(($estimate->high - $estimate->low) * 100, 1) }}%"></i><i class="pt" style="left:{{ round($estimate->rate * 100, 1) }}%"></i></span>{{ $estimate->percent() }}
@else
<span class="mut">–</span>
@endif
