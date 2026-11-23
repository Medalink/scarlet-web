@php
    $head = $d['head'];
    $aside = $d['aside'];
@endphp
<x-layouts.sc :title="$d['title']" description="Every feat of five tiers, and the share of characters that hold each tier." current="feats" :signed-in="$d['signedIn']" canonical="/feats" :index="$d['mode'] === 'everyone'">
<div class="wrap pb-20 pt-10">
  <div class="flex flex-wrap items-end gap-x-8 gap-y-4">
    <h1 class="sc-title text-[40px] md:text-[60px]">{{ $d['title'] }}</h1>
    <div class="ml-auto flex flex-wrap items-center gap-3">
      <x-sc.scope :current="$d['mode']" url="/feats" :characters="$d['names']" :as="$d['as']" :signed-in="count($d['names']) > 0" :everyone-param="true" />
    </div>
  </div>

  @if ($d['rarest'])
    <p class="sc-note mt-4"><span class="sc-label">Rarest feat earned this week</span> <a href="{{ $d['rarest']['href'] }}">{{ $d['rarest']['text'] }}</a></p>
  @endif

  @if ($head)
    <section class="mt-8 grid gap-4 lg:grid-cols-[330px_minmax(0,1fr)_230px]">
      <div class="sc-panel flex items-center gap-5 px-6 py-5">
        <x-sc.ring :value="$head['ring']" :ticks="48" :size="124"><span class="sc-num sc-num-xl">{{ $head['held'] }}</span><br><span class="sc-label">of {{ $head['total'] }}</span></x-sc.ring>
        <div>
          <p class="sc-h3">{{ $head['first'] }} holds {{ $head['held'] }} {{ $head['held'] === 1 ? 'feat' : 'feats' }}</p>
          <p class="sc-sub mt-1" style="color:var(--muted)">{{ $head['line'] }}</p>
        </div>
      </div>
      <div class="sc-grid grid-cols-2 md:grid-cols-5">
        @foreach ($head['tiles'] as $tile)
          <div class="sc-tile">
            <x-sc.seal glyph="crown" family="journey" :tier="$tile['tier']" :size="28" :label="$tile['name']" />
            <span class="sc-num sc-num-xl">{{ $tile['count'] }}</span>
            <span class="sc-label">{{ $tile['name'] }}</span>
          </div>
        @endforeach
      </div>
      <div class="sc-panel flex flex-col justify-center px-5 py-4">
        <span class="sc-label">Feat score</span>
        <span class="sc-num sc-num-xl mt-1">{{ $head['score'] }}<small>of {{ $head['max'] }}</small></span>
        @if ($head['place'])<span class="sc-sub mt-2" style="color:var(--muted)"><a href="/leaderboards/feat-score">{{ $head['place'] }}</a></span>@endif
      </div>
    </section>
  @endif

  @if ($d['closest'] !== [])
    <section class="mt-12">
      <h2 class="sc-h text-[30px]">Closest to the next tier</h2>
      <div class="mt-5 grid gap-4 md:grid-cols-3">
        @foreach ($d['closest'] as $card)
          <a href="{{ $card['href'] }}" class="sc-panel flex min-w-0 items-center gap-5 px-5 py-5 no-underline">
            <x-sc.seal :glyph="$card['glyph']" :family="$card['family']" :tier="$card['tier']" :progress="$card['progress']" :size="88" :spell="$card['spell']" :label="$card['name']" />
            <span class="min-w-0">
              <span class="sc-feat-n">{{ $card['name'] }} @if ($card['tier'])<span class="sc-chip t{{ $card['tier'] }}">{{ App\View\Sc\Seal::TIER_NAMES[$card['tier']] }}</span>@endif</span>
              <span class="sc-num sc-num-md mt-2 block">{{ $card['left'] }}</span>
              <span class="sc-sub mt-1 block" style="color:var(--muted)">{{ $card['counted'] }}</span>
            </span>
          </a>
        @endforeach
      </div>
    </section>
  @endif

  <section class="mt-14 grid gap-10 lg:grid-cols-[minmax(0,1fr)_330px]">
    <div>
      <div class="flex flex-wrap items-center gap-x-5 gap-y-3">
        <h2 class="sc-h text-[30px]">{{ $d['who'] }}</h2>
        <form class="sc-field ml-auto" method="get" action="/feats" role="search">
          @foreach ($d['hidden'] as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
          <input type="search" name="q" value="{{ $d['search'] }}" placeholder="Find a feat" aria-label="Find a feat" maxlength="40">
        </form>
      </div>
      @if ($d['tierChips'] !== [])
        <nav class="mt-4 flex flex-wrap gap-2" aria-label="Tier">@foreach ($d['tierChips'] as $chip)<a href="{{ $chip['href'] }}" @class(['sc-chipbtn no-underline', 'on' => $chip['on']])>{{ $chip['label'] }}</a>@endforeach</nav>
      @endif
      <nav class="mt-2 flex flex-wrap gap-2" aria-label="Family">@foreach ($d['familyChips'] as $chip)<a href="{{ $chip['href'] }}" @class(['sc-chipbtn no-underline', 'on' => $chip['on']])>{{ $chip['label'] }}</a>@endforeach</nav>
      <div class="mt-6" id="wall">
        @forelse ($d['wall'] as $collection)
          <x-sc.collection :name="$collection['name']" :family="$collection['family']" :feats="$collection['feats']" :counts="$d['mode'] !== 'everyone'" />
        @empty
          <p class="sc-note">No feat matches.</p>
        @endforelse
      </div>
    </div>

    @if ($aside)
      <aside id="feat" class="sc-aside sc-panel self-start px-5 py-5 lg:sticky lg:top-6">
        <x-sc.feat :feat="['glyph' => $aside['glyph'], 'family' => $aside['family'], 'tier' => $aside['tier'], 'progress' => $aside['progress'], 'spell' => $aside['spell'], 'name' => $aside['name'], 'count' => $aside['count'], 'to' => $aside['to']]" :size="64" />
        <x-sc.ladder class="mt-6" :rows="$aside['ladder']" :feat="$aside" />
        <p class="sc-label mt-5">{{ $aside['shares'] ? 'Share of characters holding each tier or higher' : 'Shares appear once 20 characters are counted' }}</p>
        <hr class="sc-rule my-4">
        <p class="flex items-center gap-3 text-[13.5px]"><a href="{{ $aside['href'] }}">Open {{ $aside['name'] }}</a></p>
      </aside>
    @endif
  </section>
</div>
</x-layouts.sc>
