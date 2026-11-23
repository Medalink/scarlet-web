@props(['spec', 'height' => 200])
<div {{ $attributes }} data-days="{{ json_encode($spec, JSON_THROW_ON_ERROR) }}" data-h="{{ $height }}" style="min-height:{{ $height }}px"></div>
