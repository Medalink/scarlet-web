@props(['value', 'ticks' => 36, 'size' => 120, 'color' => '#ff2d55'])
@php
    $lit = (int) round(max(0.0, min(1.0, (float) $value)) * $ticks);
    $inner = $size < 60 ? 30 : 40;
    $stroke = $size < 60 ? 6 : 2.6;
@endphp
<span {{ $attributes->class('sc-ring relative inline-block leading-[0]') }} style="width:{{ $size }}px;height:{{ $size }}px">
  <svg viewBox="0 0 100 100" width="{{ $size }}" height="{{ $size }}" aria-hidden="true">
    @for ($i = 0; $i < $ticks; $i++)
      @php($a = ($i / $ticks) * M_PI * 2 - M_PI / 2)
      <line @class(['on' => $i < $lit]) style="--i:{{ $i }}" x1="{{ round(50 + cos($a) * $inner, 2) }}" y1="{{ round(50 + sin($a) * $inner, 2) }}" x2="{{ round(50 + cos($a) * 48, 2) }}" y2="{{ round(50 + sin($a) * 48, 2) }}" stroke="{{ $i < $lit ? $color : 'rgba(255,255,255,0.16)' }}" stroke-width="{{ $i < $lit ? $stroke : round($stroke * 0.7, 2) }}"/>
    @endfor
  </svg>
  <span class="absolute inset-0 grid place-items-center text-center leading-[1.1]">{{ $slot }}</span>
</span>
