@props(['values', 'form' => 'line', 'w' => 96, 'h' => 26, 'color' => '#ff2d55'])
<span {{ $attributes->class('block leading-[0]') }}>{!! App\View\Sc\Spark::svg($values, $form, $w, $h, $color) !!}</span>
