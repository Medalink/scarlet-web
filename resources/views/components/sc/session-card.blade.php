@props(['recap', 'href' => null, 'chip' => null, 'title' => null, 'heading' => 32])
@php($totals = App\View\Sc\RecapView::totals($recap))
<a {{ $attributes->class('sc-panel block px-5 py-4 text-copy no-underline') }} style="--surface:var(--panel)"@if ($href) href="{{ $href }}"@endif>
  <div class="flex items-baseline gap-3 text-[13.5px] text-muted">
    <span>{{ App\View\Dates::dayTime($recap['start']) }} to {{ App\View\Dates::clock($recap['end']) }} · {{ App\View\Format::duration($recap['played']) }}</span>
    @if ($chip)<span class="ml-auto flex items-center gap-2"><x-crest :glyph="$chip['glyph']" :family="$chip['family'] ?? null" :tier="$chip['tier']" :size="18" />{{ $chip['name'] }}, {{ App\View\Sc\Crest::TIER_NAMES[$chip['tier']] }}</span>@endif
  </div>
  <p class="sc-title mt-1.5" style="font-size:{{ $heading }}px">{{ $title ?? $recap['title']['text'] }}</p>
  <x-sc.strip class="mt-3" :recap="$recap" :compact="true" />
  <p class="mt-1 text-[14px]">@foreach ($totals as [$value, $label])<b class="text-fg">{{ $value }}</b> {{ $label }}@unless ($loop->last) · @endunless @endforeach</p>
</a>
