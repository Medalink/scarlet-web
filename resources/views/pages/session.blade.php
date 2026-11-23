@php
    $recap = $d['recap'];
    $played = App\View\Format::duration($recap['played']);
    $startAt = gmdate('c', $recap['start']);
    $endAt = gmdate('c', $recap['end']);
    $hasRoute = App\View\Sc\RecapView::route($recap) !== null;
    $shown = fn (array $rows): bool => $rows !== [];
@endphp
<x-layouts.sc :title="$d['title'].' · '.$d['name'].' · Scarlet'" :description="$d['name'].', '.$d['line'].': '.$played.' played, '.gmdate('j F Y', $recap['start']).'.'" :canonical="$d['href'].'/sessions/'.$recap['start']" :index="false">
<div class="wrap pb-20 pt-8" style="--cls:{{ $d['color'] }}">
  <div class="flex flex-wrap items-start gap-6">
    <div class="min-w-0">
      <p class="text-[14.5px]"><a href="{{ $d['href'] }}" style="color:var(--cls);font-weight:700">{{ $d['name'] }}</a> @if ($d['line'])<span class="text-muted">· {{ $d['line'] }}</span>@endif</p>
      <h1 class="sc-title mt-3 text-[40px] md:text-[60px]">{{ $d['title'] }}</h1>
      <p class="mt-3 text-[15px]"><time datetime="{{ $startAt }}" data-local="D j M Y">{{ gmdate('D j M Y', $recap['start']) }}</time> · <time datetime="{{ $startAt }}" data-local="H:i">{{ gmdate('H:i', $recap['start']) }}</time> to <time datetime="{{ $endAt }}" data-local="H:i">{{ gmdate('H:i', $recap['end']) }}</time> · {{ $played }} played</p>
      @if ($d['say'])<p class="sc-say mt-4">{{ $d['say'] }}</p>@endif
    </div>
    <div class="ml-auto flex gap-2 pt-1">
      @if ($d['earlier'])
        <a class="sc-btn quiet small" href="{{ $d['earlier'] }}" aria-label="Earlier session">&#8592; <time datetime="{{ gmdate('c', $d['earlierAt']) }}" data-local="j M">{{ gmdate('j M', $d['earlierAt']) }}</time></a>
      @endif
      @if ($d['later'])
        <a class="sc-btn quiet small" href="{{ $d['later'] }}" aria-label="Later session">Later &#8594;</a>
      @endif
    </div>
  </div>

  <section class="mt-7 grid gap-4 {{ $hasRoute ? 'lg:grid-cols-[minmax(0,1fr)_448px]' : '' }}">
    @if ($hasRoute)
      <div class="sc-panel relative overflow-hidden" style="min-height:356px">
        <x-sc.route :recap="$recap" :height="356" />
        <div class="sc-key absolute bottom-3 left-4" style="--surface:var(--panel)">
          <span style="--c:var(--accent)">Walked, newest brightest</span>
          <span style="--c:var(--m-flight)">Flown</span>
        </div>
        <a href="{{ $d['map'] }}" class="sc-btn quiet small absolute right-3 top-3" style="background:var(--panel)">Open the map</a>
      </div>
    @endif
    @if ($shown($d['tiles']))
      <x-sc.tiles :cols="$hasRoute ? 2 : 4">
        @foreach ($d['tiles'] as $tile)
          <x-sc.tile :label="$tile['label']" :value="$tile['value']" :unit="$tile['unit'] ?? null" :sub="$tile['sub']" size="large">
            @if ($tile['best'])<span class="sc-best">Best of the last 30 sessions</span>@endif
            @if ($tile['label'] === 'Distance' && $shown($d['distance']))<x-sc.stack :rows="$d['distance']" :key="false" :height="8" class="mt-1" />@endif
          </x-sc.tile>
        @endforeach
      </x-sc.tiles>
    @endif
  </section>

  <section class="sc-panel mt-4 px-5 pb-3 pt-4" style="--surface:var(--panel)">
    <div class="mb-3 flex flex-wrap items-baseline gap-x-4 gap-y-1">
      <h2 class="sc-h">Experience every five minutes</h2>
      <div class="sc-key ml-auto"><span style="--c:var(--accent)">XP</span><span style="--c:var(--fg)">Level reached</span><span style="--c:var(--coin-silver)">Feat earned</span><span style="--c:var(--muted)">Quest turned in</span></div>
    </div>
    <x-sc.strip :recap="$recap" />
  </section>

  @php($hasFeats = $shown($d['feats']['earned']) || $shown($d['feats']['near']))
  @if ($hasFeats || $shown($d['own']) || $d['peers'])
    <section class="mt-4 grid gap-4 {{ $hasFeats && ($shown($d['own']) || $d['peers']) ? 'lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.5fr)]' : '' }}">
      @if ($hasFeats)
        <div class="sc-panel px-5 py-4">
          <h2 class="sc-h">Feats this session</h2>
          @foreach ($d['feats']['earned'] as $feat)
            <x-sc.feat class="mt-4" :feat="$feat" :size="104" />
          @endforeach
          @if ($shown($d['feats']['near']))
            @if ($shown($d['feats']['earned']))<hr class="sc-rule my-4">@endif
            <p class="sc-label mb-3 {{ $shown($d['feats']['earned']) ? '' : 'mt-4' }}">Closest when the session ended</p>
            <div class="grid gap-3">
              @foreach ($d['feats']['near'] as $feat)
                <x-sc.feat :feat="$feat" :size="48" />
              @endforeach
            </div>
          @endif
        </div>
      @endif
      @if ($shown($d['own']) || $d['peers'])
        <div class="sc-panel px-5 py-4" style="--surface:var(--panel)">
          <div class="grid gap-8 {{ $shown($d['own']) && $d['peers'] ? 'lg:grid-cols-2' : '' }}">
            @if ($shown($d['own']))
              <div>
                <h2 class="sc-h">Against {{ $d['first'] }}'s last {{ $d['ownCount'] }} sessions</h2>
                <div class="sc-key mt-2" style="--surface:var(--panel)"><span style="--c:var(--accent)">This session</span><span style="--c:var(--s-0)">Middle half</span></div>
                <x-sc.compare class="mt-4 max-w-[640px]" :rows="$d['own']" :n="$d['ownCount']" :min="1" :label="118" />
              </div>
            @endif
            @if ($d['peers'])
              <div>
                <h2 class="sc-h">{{ $d['peers']['heading'] }}</h2>
                <div class="sc-key mt-2" style="--surface:var(--panel)"><span style="--c:var(--accent)">This session</span><span style="--c:var(--s-0)">{{ $d['peers']['note'] }}</span></div>
                <x-sc.compare class="mt-4 max-w-[640px]" :rows="$d['peers']['rows']" :n="$d['recap']['compare']['sessions']" :label="118" />
                <p class="sc-note mt-4">The number at the right is the share of sessions this one beats.</p>
              </div>
            @endif
          </div>
        </div>
      @endif
    </section>
  @endif

  <section class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)]">
    @if ($shown($d['time']))
      <div class="sc-panel px-5 py-4">
        <h2 class="sc-h">Where the time went</h2>
        @foreach ($d['time'] as $block)
          <p class="sc-label mb-2 {{ $loop->first ? 'mt-3' : 'mt-5' }}">{{ $block['heading'] }}</p>
          <x-sc.stack :rows="$block['rows']" :height="12" />
        @endforeach
      </div>
    @endif
    @if ($shown($d['xpRows']) || $shown($d['levelRows']))
      <div class="sc-panel px-5 py-4">
        <h2 class="sc-h">Experience by source</h2>
        @if ($shown($d['xpRows']))<x-sc.stack class="mt-4" :rows="$d['xpRows']" :height="12" />@endif
        @if ($shown($d['levelRows']))
          <p class="sc-label mb-2 mt-5">Time on each level</p>
          <x-sc.bars :rows="$d['levelRows']" />
        @endif
      </div>
    @endif
    @if ($shown($d['killed']))
      <div class="sc-panel px-5 py-4">
        <h2 class="sc-h">Killed most</h2>
        <x-sc.bars class="mt-4" :rows="$d['killed']" />
      </div>
    @endif
  </section>

  @if ($shown($d['counts']))
    <x-sc.tiles class="mt-4" :cols="min(5, count($d['counts']))">
      @foreach ($d['counts'] as $count)
        <x-sc.tile :label="$count['label']" :value="$count['value']" :sub="$count['sub']" />
      @endforeach
    </x-sc.tiles>
  @endif

  <section class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
    @if ($shown($d['moments']))
      <div class="sc-panel px-5 py-4">
        <h2 class="sc-h">Levels, deaths and feats</h2>
        <div class="mt-3">
          @foreach ($d['moments'] as $moment)
            <div class="sc-moment">
              @if ($moment['at'] === null)<span class="sc-mono"></span>@else<time class="sc-mono" datetime="{{ gmdate('c', $recap['start'] + $moment['at']) }}" data-local="H:i">{{ gmdate('H:i', $recap['start'] + $moment['at']) }}</time>@endif
              <x-sc.glyph :name="$moment['glyph']" :size="16" />
              <span>{{ $moment['text'] }}</span>
            </div>
          @endforeach
        </div>
      </div>
    @endif
    @if ($shown($d['questZones']) || $shown($d['looted']))
      <div class="sc-panel px-5 py-4">
        <h2 class="sc-h">Quests and loot</h2>
        @if ($shown($d['questZones']))
          <p class="sc-label mb-2 mt-3">Quests turned in, by zone</p>
          <x-sc.bars :rows="$d['questZones']" />
        @endif
        @if ($shown($d['looted']))
          <p class="sc-label mb-2 mt-5">Looted most</p>
          <x-sc.bars :rows="$d['looted']" />
        @endif
      </div>
    @endif
  </section>
</div>
</x-layouts.sc>
