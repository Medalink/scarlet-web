{{-- The best hit of a search as one large line. best: a SearchPage::hit() line with `icon`, a name of the icon component, and `noun`, what kind of thing it is. --}}
@props(['best'])
<h2 class="vh">{{ $best['fuzzy'] ? 'Closest match' : 'Best match' }}</h2>
<a class="top-hit" href="{{ $best['href'] }}">
  <x-icon :name="$best['icon']" class="top-ico" />
  <span class="top-main">
    @if (! empty($best['class']))<b data-class="{{ $best['class'] }}"><i class="dotc" aria-hidden="true"></i><x-search-marks :marks="$best['marks']" /></b>@else<b @class([$best['quality'] ?? null])><x-search-marks :marks="$best['marks']" /></b>@endif
    <span class="top-sub"><i class="srch-noun">{{ $best['noun'] }}</i>@if ($best['meta'] !== '') {{ $best['meta'] }}@endif</span>
  </span>
  @if ($best['value'] !== '')<span class="top-val">{{ $best['value'] }}</span>@endif
</a>
