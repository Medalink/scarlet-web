<x-layouts.sc title="Scarlet: WoW Forever in numbers" description="Characters, levelling, deaths, feats and loot of WoW Forever, from players who recorded their own play with Scarlet." current="" canonical="/">
<div class="wrap pb-20 pt-12">
  <section class="grid grid-cols-[minmax(0,1fr)] items-end gap-10 lg:grid-cols-[minmax(0,1fr)_430px]">
    <div>
      <h1 class="sc-title" style="font-size:clamp(44px,6.4vw,88px);line-height:0.9">{{ $d['headline'] }}</h1>
      <form class="sc-bigsearch mt-8" role="search" action="/search"><x-sc.glyph name="search" :size="20" /><label class="sr-only" for="hub-q">Search</label><input id="hub-q" name="q" type="search" placeholder="Search a character, item, creature, zone or quest" class="w-full bg-transparent text-fg outline-none placeholder:text-muted"></form>
    </div>
    @if ($d['tiles'] !== [])
      <div class="sc-grid grid-cols-2">
        @foreach ($d['tiles'] as $tile)
          <x-sc.tile :label="$tile['label']" :value="$tile['value']" :unit="$tile['unit']" :sub="$tile['sub']" />
        @endforeach
      </div>
    @endif
  </section>

  @if ($d['curve'] || $d['deaths'])
    <section class="mt-16 grid grid-cols-[minmax(0,1fr)] gap-10 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
      @if ($d['curve'])
        <div>
          <div class="flex items-baseline"><h2 class="sc-h" style="font-size:32px">How long each level takes</h2><a href="/stats" class="ml-auto text-[14px]">Levelling stats</a></div>
          <div class="sc-key mt-3"><span style="--c:var(--accent)">Fastest tenth</span><span style="--c:var(--muted)">Median</span><span style="--c:rgba(255,255,255,0.14)">Middle half</span></div>
          <div class="mt-3" data-line="{{ json_encode($d['curve']['spec'], JSON_THROW_ON_ERROR) }}" data-h="280" style="min-height:280px"></div>
          @if ($d['curve']['sentence'])<p class="sc-say mt-3">{!! $d['curve']['sentence'] !!}</p>@endif
        </div>
      @endif
      <div>
        @if ($d['deaths'])
          <div class="flex items-baseline"><h2 class="sc-h" style="font-size:32px">Deaths an hour, by level</h2><a href="/stats" class="ml-auto text-[14px]">Danger</a></div>
          <div class="mt-5" data-cols="{{ json_encode($d['deaths']['spec'], JSON_THROW_ON_ERROR) }}" data-h="200" style="min-height:200px"></div>
          <p class="sc-say mt-3">{!! $d['deaths']['sentence'] !!}</p>
        @endif
        @if ($d['classes'] !== [])
          <h3 class="sc-h mt-9" style="font-size:24px">Most played classes</h3>
          <x-sc.bars class="mt-3" :rows="$d['classes']" />
        @endif
      </div>
    </section>
  @endif

  <section class="mt-16">
    <h2 class="sc-h" style="font-size:32px">Everything on the site</h2>
    <div class="mt-5">
      @foreach ($d['directory'] as $row)
        <a class="sc-dir" href="{{ $row['href'] }}"><span class="nm">{{ $row['name'] }}</span><span class="fact">{{ $row['fact'] }}@if ($row['bold']) <b>{{ $row['bold'] }}</b>@endif</span><x-sc.glyph name="arrow" :size="18" /></a>
      @endforeach
    </div>
  </section>

  <section class="mt-16 grid grid-cols-[minmax(0,1fr)] gap-10 lg:grid-cols-3">
    @foreach ([['Fastest to 60', $d['fastest'], '/leaderboards/fastest-to-60'], ['Highest level without dying', $d['clean'], '/leaderboards/highest-level-without-dying']] as [$title, $rows, $href])
      <div>
        <div class="flex items-baseline"><h2 class="sc-h" style="font-size:26px">{{ $title }}</h2><a href="{{ $href }}" class="ml-auto text-[14px]">Board</a></div>
        @if ($rows === [])
          <p class="sc-note mt-3">No public character yet.</p>
        @else
          <div class="mt-3">
            @foreach ($rows as $row)
              <a href="{{ $row['href'] }}" class="sc-pod"><span class="n {{ $row['tone'] }}">{{ $row['n'] }}</span><span><span class="sc-cls" style="--cls:{{ $row['color'] }}"></span> {{ $row['name'] }}</span><span class="sc-mono">{{ $row['value'] }}</span></a>
            @endforeach
          </div>
        @endif
      </div>
    @endforeach
    <div>
      <h2 class="sc-h" style="font-size:26px">When everyone plays</h2>
      @if ($d['heat'])
        <div class="sc-heat mt-4">@foreach ($d['heat'] as $cell)<i data-v="{{ $cell['level'] }}" data-tip="{{ $cell['tip'] }}"></i>@endforeach</div>
        <div class="sc-label mt-1.5 flex justify-between"><span>00:00</span><span>06:00</span><span>12:00</span><span>18:00</span><span>23:00</span></div>
      @else
        <p class="sc-note mt-3">Not enough play time recorded yet.</p>
      @endif
    </div>
  </section>

  <section class="sc-panel mt-16 grid grid-cols-[minmax(0,1fr)] items-center gap-6 px-7 py-6 md:grid-cols-[minmax(0,1fr)_auto]">
    <div>
      <h2 class="sc-h" style="font-size:30px">Record your own characters</h2>
      <p class="mt-2 text-[15.5px]" style="max-width:62ch">The Scarlet addon records what you do in game. The desktop app uploads it, and your characters, sessions and feats show up here. Uploading works without an account; linking the app to an account lets you choose what is public.</p>
    </div>
    <div class="flex gap-3"><a href="/download" class="sc-btn">Get the app</a></div>
  </section>
</div>
</x-layouts.sc>
