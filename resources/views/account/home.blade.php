<x-account.shell title="Account" current="/account" :heading="$d['title']">
@if (! $d['empty'])
  <section class="mt-10" id="characters">
    <h2 class="sc-h" style="font-size:30px">Characters</h2>
    <livewire:account.characters />
    <p class="mt-4 text-[14px]"><a href="/stats?scope=compare">Your stats against everyone</a> · <a href="/professions?scope=mine">Your professions</a> · <a href="/camps?scope=mine">Your camps</a> · <a href="/map?scope=mine">Your map</a></p>
  </section>
@endif

  <section class="mt-12 grid grid-cols-[minmax(0,1fr)] gap-x-12 gap-y-10 lg:grid-cols-2">
    <div id="sign-in">
      <h2 class="sc-h" style="font-size:30px">Sign-in methods</h2>
      <p class="sc-note mt-2">Scarlet stores the sign-in's id and nothing else about you: no email, no name.</p>
      <livewire:account.sign-in-methods />
    </div>
    <div id="installs">
      <h2 class="sc-h" style="font-size:30px">Linked installs</h2>
      <livewire:account.installs />
    </div>
  </section>

@if ($d['empty'])
  <section class="mt-12">
    <x-sc.link-row dot="off" action="Get the app" href="/download">No characters yet. Open Scarlet on your PC and choose Link to my account.</x-sc.link-row>
  </section>
@else
  <section class="mt-12 grid grid-cols-[minmax(0,1fr)] gap-4 lg:grid-cols-[minmax(0,1fr)_360px]">
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
        <div class="mt-4"><x-week-heat :spec="$d['heat']['spec']" :line="$d['heat']['line']" /></div>
      @else
        <p class="sc-note mt-4">Nothing recorded in the last 12 weeks.</p>
      @endif
    </div>
  </section>
@endif
</x-account.shell>
