{{-- Two counts as one bar and a key line. split: App\View\Pages\StatsGame Split. --}}
@props(['split'])
<div class="split" role="img" aria-label="{{ $split['label'] }}">@foreach ($split['sides'] as $side)<i class="{{ $side['tone'] }}" style="flex:{{ $side['count'] }}"></i>@endforeach</div>
<p class="split-key">@foreach ($split['sides'] as $side)@if ($loop->first)<span class="{{ $side['tone'] }}"><i></i><b>{{ number_format($side['count']) }}</b> {{ $side['name'] }}</span>@else<span class="{{ $side['tone'] }}">{{ $side['name'] }} <b>{{ number_format($side['count']) }}</b><i></i></span>@endif @endforeach</p>
