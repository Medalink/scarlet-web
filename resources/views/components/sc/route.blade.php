@props(['recap', 'height' => 154])
@php($spec = App\View\Sc\RecapView::route($recap))
@if ($spec)
<div {{ $attributes->class('overflow-hidden') }} data-route="{{ json_encode($spec, JSON_THROW_ON_ERROR) }}" data-h="{{ $height }}" style="height:{{ $height }}px"></div>
@endif
