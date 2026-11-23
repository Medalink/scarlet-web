@props(['glyph', 'tier' => 0, 'progress' => 0, 'size' => 64, 'family' => null, 'label' => '', 'ghost' => 0, 'spell' => null])
<span {{ $attributes->class(['sc-seal', 't'.$tier]) }}>{!! App\View\Sc\Seal::svg($glyph, (int) $tier, (float) $progress, (int) $size, $family, $label, (int) $ghost, $spell) !!}</span>
