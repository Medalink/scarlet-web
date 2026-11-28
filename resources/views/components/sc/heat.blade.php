@props(['cells', 'hours' => [0, 0.4, 1.2, 2.5, 4.1, 6.3]])
@php($days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'])
<div {{ $attributes->class('sc-heat') }}>
  @foreach ($cells as $i => $level)<i data-v="{{ $level }}" data-tip="{{ $days[intdiv($i, 24)] }} {{ \App\View\Dates::hour($i % 24) }}|{{ $hours[$level] }} hours"></i>@endforeach
</div>
