@props(['glyph', 'tier' => 0, 'progress' => 0, 'size' => 64, 'label' => '', 'state' => '', 'scope' => null, 'featKey' => null, 'family' => null, 'spell' => null, 'icon' => null])
@php
    $scope ??= App\View\Sc\Crest::scopeOf($featKey, $family);
    $icon ??= $spell === null ? null : App\Support\SpellIcons::icon((int) $spell);
@endphp
<span {{ $attributes->class(['sc-crest', 't'.$tier, 's-'.$scope]) }}>{!! App\View\Sc\Crest::svg($glyph, $scope, (int) $tier, (float) $progress, (int) $size, $label, $state, $icon) !!}</span>
