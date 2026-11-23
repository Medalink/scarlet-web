
<section id="feats" class="sc-sec">
  <div class="sc-sec-head"><h2>Feats</h2><p class="ml-auto pb-1 text-[14px] text-muted">{{ $f['summary']['held'] }} of {{ $f['summary']['total'] }} held @if ($f['hidden'])· {{ $f['hidden'] }}@endif · <a href="/feats">All feats</a></p></div>
  @if ($f['pinned'] !== [])
    <div class="mt-7 grid max-w-[720px] grid-cols-3 gap-4 text-center">
      @foreach ($f['pinned'] as $feat)
        <a href="{{ $feat['href'] }}" class="no-underline">
          <x-sc.seal :glyph="$feat['glyph']" :family="$feat['family']" :tier="$feat['tier']" :progress="$feat['progress']" :size="104" :spell="$feat['spell']" :label="$feat['name']" />
          <p class="sc-feat-n mt-3 justify-center">{{ $feat['name'] }} <span class="sc-chip t{{ $feat['tier'] }}">{{ $feat['tierName'] }}</span></p>
          <p class="sc-sub mt-2">{{ $feat['counted'] }}</p>
        </a>
      @endforeach
    </div>
  @endif
  @if ($f['closest'] !== [])
    <h3 class="sc-h mt-9" style="font-size:24px">Closest to the next tier</h3>
    <div class="mt-4 grid gap-4 md:grid-cols-3">
      @foreach ($f['closest'] as $card)
        <a href="{{ $card['href'] }}" class="sc-panel flex min-w-0 items-center gap-5 px-5 py-5 no-underline">
          <x-sc.seal :glyph="$card['glyph']" :family="$card['family']" :tier="$card['tier']" :progress="$card['progress']" :size="72" :spell="$card['spell']" :label="$card['name']" />
          <span class="min-w-0">
            <span class="sc-feat-n">{{ $card['name'] }}</span>
            <span class="sc-num sc-num-md mt-2 block">{{ $card['left'] }}</span>
            <span class="sc-sub mt-1 block" style="color:var(--muted)">{{ $card['counted'] }}</span>
          </span>
        </a>
      @endforeach
    </div>
  @endif
  <div class="mt-8">
    @foreach ($f['wall'] as $collection)
      <x-sc.collection :name="$collection['name']" :family="$collection['family']" :feats="$collection['feats']" :pin="$pin" />
    @endforeach
  </div>
</section>
