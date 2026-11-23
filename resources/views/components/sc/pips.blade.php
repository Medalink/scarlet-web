@props(['tier' => 0])
<span {{ $attributes->class('sc-pips') }}>{!! App\View\Sc\Seal::pipRow((int) $tier) !!}</span>
