@props(['name', 'family', 'feats', 'size' => 48, 'note' => null, 'counts' => true, 'pin' => false, 'full' => false])
@php
    $earned = count(array_filter($feats, fn (array $feat): bool => $feat['tier'] > 0));
    $tier = min(array_column($feats, 'tier'));
    $names = App\View\Sc\Seal::TIER_NAMES;
@endphp
<div {{ $attributes->class('sc-coll') }}>
  <div>
    <p class="sc-coll-h">{{ $name }}</p>
    @if ($counts)<p class="sc-coll-n">{{ $earned }} of {{ count($feats) }} <x-sc.pips :tier="$tier" /></p>@endif
    @if ($note)<p class="sc-note mt-2 max-w-[150px]">{{ $note }}</p>@endif
  </div>
  <ul class="sc-wall">
    @foreach ($feats as $feat)
      @php($next = $names[min(5, $feat['tier'] + 1)])
      <li @class(['t'.$feat['tier'], 'on' => $feat['on'] ?? false, 'relative']) data-tip="{{ $feat['name'] }}|{{ $feat['tier'] ? $names[$feat['tier']] : 'Not earned yet' }} · {{ round(($feat['progress'] ?? 0) * 100) }}% of the way to {{ $next }}">
        <x-sc.seal :glyph="$feat['glyph']" :family="$feat['family'] ?? $family" :tier="$feat['tier']" :progress="$feat['progress'] ?? 0" :size="$size" :spell="$feat['spell'] ?? null" />
        <span>{{ $feat['name'] }}@isset ($feat['share'])<small>{{ $feat['share'] }}</small>@endisset</span>
        @isset ($feat['href'])<a class="absolute inset-0" href="{{ $feat['href'] }}" aria-label="{{ $feat['name'] }}"></a>@endisset
        @if ($pin && $feat['tier'] > 0 && isset($feat['key']))<button type="button" @class(['sc-pin', 'on' => $feat['pinned'] ?? false]) @disabled($full && ! ($feat['pinned'] ?? false)) wire:click="{{ ($feat['pinned'] ?? false) ? 'unpin' : 'pin' }}('{{ $feat['key'] }}')" aria-pressed="{{ ($feat['pinned'] ?? false) ? 'true' : 'false' }}" aria-label="{{ ($feat['pinned'] ?? false) ? 'Unpin' : 'Pin' }} {{ $feat['name'] }}">{{ ($feat['pinned'] ?? false) ? 'Pinned' : 'Pin' }}</button>@endif
      </li>
    @endforeach
  </ul>
</div>
