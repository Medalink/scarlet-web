@props(['label', 'value' => null, 'unit' => null, 'sub' => null, 'size' => null])
<div {{ $attributes->class('sc-tile') }}>
  <span class="sc-label">{{ $label }}</span>
  @if ($value !== null)<span @class(['sc-num', 'sc-num-xl' => $size === 'large', 'sc-num-md' => $size === 'small'])>{{ $value }}@if ($unit)<small>{{ $unit }}</small>@endif</span>@endif
  @if ($sub)<span class="sc-sub">{{ $sub }}</span>@endif
  {{ $slot }}
</div>
