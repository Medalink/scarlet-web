@props(['feat', 'kicker', 'play' => false])
@php($tier = (int) $feat['tier'])
<div {{ $attributes->class(['sc-toast', 't'.$tier, 'play' => $play]) }} role="status">
  <i></i>
  <span class="relative grid place-items-center"><span class="glow"></span><span class="glow glow2"></span><x-crest :glyph="$feat['glyph']" :feat-key="$feat['key'] ?? null" :scope="$feat['scope'] ?? null" :family="$feat['family'] ?? null" :tier="$tier" :progress="$feat['progress'] ?? 0" :size="64" :spell="$feat['spell'] ?? null" state="earned" :label="$feat['name']" /></span>
  <div class="txt">
    <p class="k">{{ $kicker }}</p>
    <p class="sc-feat-n">{{ $feat['name'] }} <span class="sc-chip t{{ $tier }}">{{ App\View\Sc\Crest::TIER_NAMES[$tier] }}</span></p>
    <p class="d">{{ $feat['count'] }}</p>
  </div>
</div>
