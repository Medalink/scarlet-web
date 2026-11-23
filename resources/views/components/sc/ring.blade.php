@props(['value', 'size' => 120, 'color' => '#ff2d55'])
@php
    $share = round(max(0.0, min(1.0, (float) $value)) * 100, 2);
    $stroke = $size < 60 ? 10 : 5;
    $r = 50 - $stroke / 2;
@endphp
<span {{ $attributes->class('sc-ring relative inline-block leading-[0]') }} style="width:{{ $size }}px;height:{{ $size }}px">
  <svg viewBox="0 0 100 100" width="{{ $size }}" height="{{ $size }}" aria-hidden="true">
    <circle cx="50" cy="50" r="{{ $r }}" fill="none" stroke="rgba(255,255,255,0.10)" stroke-width="{{ $stroke }}"/>
    @if ($share > 0)
      <circle class="arc" cx="50" cy="50" r="{{ $r }}" fill="none" stroke="{{ $color }}" stroke-width="{{ $stroke }}" stroke-linecap="round" pathLength="100" stroke-dasharray="{{ $share }} 100" transform="rotate(-90 50 50)"/>
    @endif
  </svg>
  <span class="absolute inset-0 flex flex-col items-center justify-center text-center leading-[1.1]">{{ $slot }}</span>
</span>
