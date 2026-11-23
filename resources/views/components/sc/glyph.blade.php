@props(['name', 'size' => 16, 'width' => 1.7])
<span {{ $attributes->class(['inline-block leading-none']) }}>{!! App\View\Sc\Glyphs::svg($name, $size, $width) !!}</span>
