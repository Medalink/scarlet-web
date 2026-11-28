<x-layouts.sc title="Your characters · Scarlet" current="account" :signed-in="true" :index="false" :brand="false">
<div class="wrap pb-20 pt-10">
  <div class="flex flex-wrap items-end gap-x-6 gap-y-2"><h1 class="sc-title" style="font-size:60px">{{ $d['title'] }}</h1><a href="/account/settings" class="sc-btn quiet small mb-2 ml-auto">Settings</a></div>
@if (session('notice'))
  <p class="sc-note mt-4" role="status">{{ session('notice') }}</p>
@endif

@if ($d['empty'])
  <section class="mt-8">
    <x-sc.link-row dot="off" action="Get the app" href="/download">No characters yet. Open Scarlet on your PC and choose Link to my account.</x-sc.link-row>
  </section>
@else
  <section class="mt-8 grid grid-cols-[minmax(0,1fr)] gap-4 lg:grid-cols-[minmax(0,1fr)_360px]">
    @if ($d['latest'])
      <div>
        <p class="sc-label mb-2"><span class="sc-cls" style="--cls:{{ $d['latest']['color'] }}"></span> <b class="font-semibold text-fg">{{ $d['latest']['name'] }}</b> · latest session</p>
        <x-sc.session-card :recap="$d['latest']['recap']" :href="$d['latest']['href']" :heading="38" />
      </div>
    @endif
    <div class="sc-grid grid-cols-2 self-start">
      @foreach ($d['week'] as $tile)
        <x-sc.tile :label="$tile['label']" :value="$tile['value']" :sub="$tile['sub']">
          @if ($tile['spark'])<x-sc.spark :values="$tile['spark']" form="cols" :w="120" :h="26" />@endif
          @if ($tile['crests'])<span class="flex gap-1.5">@foreach ($tile['crests'] as $crest)<x-crest :glyph="$crest['glyph']" :feat-key="$crest['key']" :tier="$crest['tier']" :size="28" :label="$crest['name']" />@endforeach</span>@endif
        </x-sc.tile>
      @endforeach
    </div>
  </section>

  <section class="mt-12">
    <h2 class="sc-h" style="font-size:30px">Characters</h2>
    <div class="sc-panel mt-4">
      @foreach ($d['rows'] as $row)
        <a href="{{ $row['href'] }}" class="sc-char" style="--cls:{{ $row['color'] }}">
          <span><span class="nm">{{ $row['name'] }}</span><span class="sc-sub mt-1 block" style="color:var(--muted)">{{ $row['line'] }}</span></span>
          <span><span class="flex items-baseline justify-between"><b class="sc-num sc-num-md">{{ $row['level'] }}</b><span class="sc-label">of 60</span></span><span class="sc-lvl mt-1.5 block"><i style="width:{{ $row['width'] }}%"></i></span></span>
          <span class="flex items-center gap-2"><x-sc.ring :value="$row['ring']" :size="40" /><span class="text-[13px]"><b class="text-fg">{{ $row['feats'] }}</b> {{ $row['feats'] === 1 ? 'feat' : 'feats' }}</span></span>
          <span><span class="block text-[13px] text-muted">{{ $row['seen'] }}</span>@if ($row['place'])<span class="sc-say mt-1 block" style="font-size:15px">{{ $row['place'] }}</span>@endif</span>
          <x-sc.glyph name="arrow" :size="18" />
        </a>
      @endforeach
    </div>
    @if ($d['more'])<p class="mt-3 text-[14px]">{{ ucfirst($d['more']) }} not shown.</p>@endif<p class="mt-3 text-[14px]"><a href="/stats?scope=compare">Your stats against everyone</a> · <a href="/professions?scope=mine">Your professions</a> · <a href="/camps?scope=mine">Your camps</a> · <a href="/map?scope=mine">Your map</a></p>
  </section>

  @if ($d['accountFeats'])
    <section class="mt-12">
      <h2 class="sc-h" style="font-size:30px">Feats</h2>
      <x-sc.collection class="mt-4" :name="$d['accountFeats']['name']" :family="$d['accountFeats']['family']" :feats="$d['accountFeats']['feats']" :count="$d['accountFeats']['held'].' of '.$d['accountFeats']['total']" />
      <p class="mt-3 text-[14px]"><a href="/feats">All feats</a></p>
    </section>
  @endif

  @if ($d['play'] || $d['gold'])
    <section class="mt-14 grid grid-cols-[minmax(0,1fr)] gap-10 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
      @if ($d['play'])
        <div>
          <div class="flex flex-wrap items-baseline gap-x-6 gap-y-2"><h2 class="sc-h" style="font-size:30px">Play time, last 30 days</h2><div class="sc-key lg:ml-auto">@foreach ($d['play']['key'] as [$label, $hours, $var])<span style="--c:{{ $var }}">{{ $label }} <b>{{ $hours }}</b></span>@endforeach</div></div>
          <x-sc.days class="mt-4" :spec="$d['play']['spec']" :height="216" />
          <p class="sc-say mt-3" style="font-size:15.5px">{!! $d['play']['sentence'] !!}</p>
        </div>
      @endif
      @if ($d['gold'])
        <div>
          <h2 class="sc-h" style="font-size:30px">Gold held</h2>
          <x-sc.area class="mt-4" :spec="$d['gold']['spec']" :height="216" />
          <p class="sc-say mt-3" style="font-size:15.5px">{!! $d['gold']['sentence'] !!}</p>
        </div>
      @endif
    </section>
  @endif

  @if ($d['compare'] || $d['levels'])
    <section class="mt-14 grid grid-cols-[minmax(0,1fr)] gap-10 lg:grid-cols-2">
      @if ($d['compare'])
        @php($c = $d['compare'])
        <div>
          <h2 class="sc-h" style="font-size:30px">{{ $c['heading'] }}</h2>
          @if ($c['key'])<div class="sc-key mt-3"><span style="--c:var(--accent)">{{ $c['name'] }}</span><span style="--c:var(--s-0)">{{ $c['key'] }}</span><span style="--c:var(--text)">Median</span></div>@endif
          <x-sc.compare class="mt-5" :rows="$c['rows']" :n="$c['n']" :label="150" />
          @if ($c['sentence'])<p class="sc-say mt-6">{{ $c['sentence'] }}</p>@endif
        </div>
      @endif
      @if ($d['levels'])
        @php($l = $d['levels'])
        <div>
          <h2 class="sc-h" style="font-size:30px">Hours on each level against the median</h2>
          <div class="sc-key mt-3"><span style="--c:var(--accent)">{{ $l['name'] }}</span><span style="--c:var(--text)">Median of the ruleset</span></div>
          <div class="mt-4" data-cols="{{ json_encode($l['cols'], JSON_THROW_ON_ERROR) }}" data-h="232" style="min-height:232px"></div>
          @if ($l['sentence'])<p class="sc-say mt-4">{{ $l['sentence'] }}</p>@endif
        </div>
      @endif
    </section>
  @endif

  <section class="mt-14 grid grid-cols-[minmax(0,1fr)] gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.1fr)]">
    <div>
      <h2 class="sc-h" style="font-size:26px">Feats within reach</h2>
      @if ($d['reach'] === [])
        <p class="sc-note mt-4">Nothing is close to its next tier yet.</p>
      @else
        <div class="mt-4 grid gap-5">
          @foreach ($d['reach'] as $feat)
            <x-sc.feat :feat="$feat" :size="52" />
          @endforeach
        </div>
      @endif
      <p class="mt-5 text-[14px]"><a href="/feats">All feats</a></p>
    </div>
    <div>
      <h2 class="sc-h" style="font-size:26px">Best places on the boards</h2>
      @if ($d['places'] === [])
        <p class="sc-note mt-4">Public characters appear on the boards.</p>
      @else
        <div class="mt-3">
          @foreach ($d['places'] as $place)
            <a href="{{ $place['href'] }}" class="sc-rk"><span class="n">{{ $place['ordinal'] }}</span><span>{{ $place['title'] }}<br><span class="sc-label">{{ $place['who'] }} · of {{ $place['of'] }}</span></span><span class="sc-mono">{{ $place['value'] }}</span></a>
          @endforeach
        </div>
      @endif
    </div>
    <div>
      <h2 class="sc-h" style="font-size:26px">When you play</h2>
      @if ($d['heat'])
        <div class="sc-heat mt-4">@foreach ($d['heat']['cells'] as $cell)<i data-v="{{ $cell['level'] }}" data-tip="{{ $cell['tip'] }}"></i>@endforeach</div>
        <div class="sc-label mt-1.5 flex justify-between"><span>12 AM</span><span>6 AM</span><span>12 PM</span><span>6 PM</span><span>11 PM</span></div>
        @if ($d['heat']['say'])<p class="mt-3 text-[14px]">{{ $d['heat']['say'] }}</p>@endif
      @else
        <p class="sc-note mt-4">Nothing recorded in the last 12 weeks.</p>
      @endif
    </div>
  </section>
@endif

  <section class="mt-14">
    @if ($d['link']['linked'])
      <x-sc.link-row dot="on" action="Settings" href="/account/settings">{{ $d['link']['text'] }}</x-sc.link-row>
    @else
      <x-sc.link-row dot="off" action="Get the app" href="/download">{{ $d['link']['text'] }}</x-sc.link-row>
    @endif
  </section>
</div>
</x-layouts.sc>
