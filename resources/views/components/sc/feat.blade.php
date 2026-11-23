@props(['feat', 'size' => 64])
@php($tier = (int) $feat['tier'])
<div {{ $attributes->class(['sc-feat', 't'.$tier]) }}>
  <x-crest :glyph="$feat['glyph']" :feat-key="$feat['key'] ?? null" :scope="$feat['scope'] ?? null" :family="$feat['family'] ?? null" :tier="$tier" :progress="$feat['progress'] ?? 0" :size="$size" :spell="$feat['spell'] ?? null" :label="$feat['name']" />
  <div>
    <p class="sc-feat-n">@isset ($feat['href'])<a class="no-underline" href="{{ $feat['href'] }}">{{ $feat['name'] }}</a>@else{{ $feat['name'] }}@endisset @if ($tier)<span class="sc-chip t{{ $tier }}">{{ App\View\Sc\Crest::TIER_NAMES[$tier] }}</span>@endif @if ($feat['new'] ?? false)<span class="sc-chip new">New</span>@endif @isset ($feat['share'])<span class="sc-feat-r">{{ $feat['share'] }}</span>@endisset</p>
    <p class="sc-feat-d">{{ $feat['count'] }}</p>
    @isset ($feat['to'])<p class="sc-feat-to">{{ $feat['to'] }}</p>@endisset
  </div>
</div>
