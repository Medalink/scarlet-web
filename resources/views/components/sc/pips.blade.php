@props(['tier' => 0])
<span {{ $attributes->class('sc-pips') }}>{!! App\View\Sc\Crest::pips((int) $tier) !!}</span>
