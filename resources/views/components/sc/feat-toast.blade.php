@props(['feat', 'kicker', 'play' => false])
@php($tier = (int) $feat['tier'])
<div {{ $attributes->class(['sc-toast', 't'.$tier, 'play' => $play]) }} role="status">
  <i></i>
  <span class="relative grid place-items-center"><span class="glow"></span><span class="glow glow2"></span><x-sc.seal :glyph="$feat['glyph']" :family="$feat['family'] ?? null" :tier="$tier" :progress="$feat['progress'] ?? 0" :size="64" :spell="$feat['spell'] ?? null" :label="$feat['name']" /></span>
  <div class="txt">
    <p class="k">{{ $kicker }}</p>
    <p class="sc-feat-n">{{ $feat['name'] }} <span class="sc-chip t{{ $tier }}">{{ App\View\Sc\Seal::TIER_NAMES[$tier] }}</span></p>
    <p class="d">{{ $feat['count'] }}</p>
  </div>
  <span class="sweep"></span>
</div>
