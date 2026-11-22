<x-layouts.app :title="$d['share']['title']" :description="$d['share']['description']" :canonical="$d['path']" :index="! $d['owner']">
<section @class(['ch-head', 'ch-rich', 'ch-bare' => ! $d['hasMap']]) aria-labelledby="h1">
  @if ($d['hasMap'])
  <div class="stage"><x-world-canvas id="map" :behind-head="true" label="Every path {{ $d['name'] }} walked, with deaths as crosses. Drag to move, click the map and scroll to zoom." :spec="$d['map']" />
    <ul class="legend ch-legend" aria-label="Map marks">
      <li><svg viewBox="0 0 24 14" aria-hidden="true"><path d="M1 7h22" stroke="#ff2d55" stroke-width="2"/></svg>every path walked</li>
      @if ($d['deathCount'])<li><svg viewBox="0 0 24 14" aria-hidden="true"><path d="M7.5 2.5l9 9M16.5 2.5l-9 9" stroke="#f0f1f3" stroke-width="1.7" stroke-linecap="round"/></svg>death</li>@endif
    </ul>
  </div>
  <button type="button" class="ch-replay" hidden>Replay</button>
  @endif
  <div class="ch-id wrap">
    <div><h1 class="ch-name" id="h1">{{ $d['name'] }}</h1>
      <p class="ch-sub" @if ($d['classKey'] !== '') data-class="{{ $d['classKey'] }}" @endif><i class="dot" aria-hidden="true"></i>@foreach ($d['facts'] as $fact)<span>@if ($fact['href'])<a href="{{ $fact['href'] }}">{{ $fact['text'] }}</a>@else{{ $fact['text'] }}@endif</span>@endforeach</p>
      <dl class="ch-figs">@foreach ($d['figures'] as $figure)<div><dt>@if ($figure['icon'])<x-icon :name="$figure['icon']" />@endif {{ $figure['label'] }}</dt><dd @if (isset($figure['count'])) data-count="{{ $figure['count'] }}" @endif>{{ $figure['value'] }}</dd></div>@endforeach</dl>
      @if ($d['levelRule'])<x-rules :rows="$d['levelRule']" />@endif</div>
  </div>
</section>
<div class="wrap">
  @if ($d['owner'])<livewire:owner-bar :character-id="$d['id']" />@endif
  @if ($d['ownerSeesVisitorView'])<div class="own"><span>Visitor view</span><span class="sp"><a href="{{ $d['path'] }}">Back to your view</a></span></div>@endif
  @if ($d['records'])
  <section class="recs" aria-labelledby="h-records">
    <h2 id="h-records">Records</h2>
    <ul class="rec-grid">@foreach ([$d['records']['lead'], ...$d['records']['tiles']] as $tile)<li @class(['rec-lead' => $loop->first, ...array_fill_keys(explode(' ', (string) $tile['span']), true)])>
      <x-icon :name="$tile['icon']" class="rec-ico" />
      <b class="rec-v">{{ $tile['value'] }}</b>
      <span class="rec-l">{{ $tile['label'] }}</span>
      @if ($tile['detail'])<span class="rec-d">{{ $tile['detail'] }}</span>@endif
      @if ($tile['extra'])<span class="rec-d">{{ $tile['extra'] }}</span>@endif
    </li>@endforeach</ul>
  </section>
  @endif
  @if ($d['feats'])
  <section class="recs" aria-labelledby="h-feats">
    <h2 id="h-feats">Feats</h2>
    <p class="feats-sum"><span><b>{{ $d['feats']['held'] }}</b>of {{ $d['feats']['total'] }} feats</span>@foreach ($d['feats']['tiers'] as $tier)<span><b>{{ $tier['count'] }}</b>{{ $tier['name'] }}</span>@endforeach</p>
    @if ($d['feats']['since'])<p class="note">Counted since {{ $d['feats']['since'] }}.</p>@endif
    <ul class="feats feats-top">@foreach ($d['feats']['top'] as $feat)<x-feat :feat="$feat" />@endforeach</ul>
    <x-btn kind="quiet" commandfor="feats-all" command="show-modal">View all</x-btn>
    <dialog id="feats-all" class="feats-all" closedby="any" aria-labelledby="h-feats-all">
      <header><h2 id="h-feats-all">Feats</h2><x-btn kind="quiet" commandfor="feats-all" command="close" aria-label="Close">&times;</x-btn></header>
      <div class="feats-body">
        @if ($d['feats']['earned'])
        <h3>Earned <span>{{ count($d['feats']['earned']) }}</span></h3>
        <ul class="feats">@foreach ($d['feats']['earned'] as $feat)<x-feat :feat="$feat" />@endforeach</ul>
        @endif
        @if ($d['feats']['unearned'])
        <h3>Not yet earned <span>{{ count($d['feats']['unearned']) }}</span></h3>
        <ul class="feats">@foreach ($d['feats']['unearned'] as $feat)<x-feat :feat="$feat" />@endforeach</ul>
        @endif
      </div>
    </dialog>
  </section>
  @endif
  @if ($d['standings'])
  <section class="recs" aria-labelledby="h-stands">
    <h2 id="h-stands">Standings</h2>
    <p class="note">Among public characters of every ruleset.</p>
    <ol class="stands">@foreach ($d['standings'] as $stand)<li @if ($stand['medal']) class="{{ $stand['medal'] }}" @endif><span class="place"><span class="vh">Place </span>{{ $stand['place'] }}</span><a class="board" href="{{ $stand['href'] }}">{{ $stand['title'] }}</a><span class="val">{{ $stand['value'] }}</span><span class="of">of {{ $stand['of'] }}</span></li>@endforeach</ol>
  </section>
  @endif
  @if ($d['week'])
  <section class="week" aria-labelledby="h-week">
    <div><h2 id="h-week">This week</h2><p>{{ $d['week']['range'] }}</p></div>
    @foreach ($d['week']['figures'] as $figure)<dl><dt>@if ($figure['icon'])<x-icon :name="$figure['icon']" />@endif {{ $figure['label'] }}</dt><dd @if (isset($figure['count'])) data-count="{{ $figure['count'] }}" @endif>{{ $figure['value'] }}</dd></dl>@endforeach
  </section>
  @endif
  <x-sheet class="chapters">
    @foreach ($d['chapters'] as $chapter)
    <h2 class="sect" id="{{ $chapter['id'] }}">{{ $chapter['title'] }}</h2>
    @foreach ($chapter['rows'] as $row)
    @foreach ($row as $m)
    @include('pages.character.'.$m['module'], ['span' => $m['span'], 'level' => $m['level'], 'first' => $loop->first, 'last' => $loop->last])
    @endforeach
    @endforeach
    @endforeach
  </x-sheet>
  @if ($d['imported'])
  <x-sheet>
    <x-module span="w12" :first="true" :last="true" id="h-imported">
      <h2 id="h-imported">Imported from {{ $d['imported']['source'] }} <span class="priv">Only you</span></h2>
      <p class="sub">{{ $d['imported']['range'] }}. Scarlet did not record these itself, so no board or other page counts them.</p>
      <x-figures :items="$d['imported']['figures']" />
      @if ($d['imported']['gold'])<x-plot id="imported-gold" label="Gold at the end of each imported day" :spec="$d['imported']['gold']" />@endif
      @if ($d['imported']['days'])<h3 class="chart-name">Sales and purchases by day</h3><x-table :columns="$d['imported']['days']['columns']" :rows="$d['imported']['days']['rows']" />@endif
      @if ($d['imported']['items'])<h3 class="chart-name">Sale prices by item</h3><x-table :columns="$d['imported']['items']['columns']" :rows="$d['imported']['items']['rows']" />@endif
    </x-module>
  </x-sheet>
  @endif
</div>
</x-layouts.app>
