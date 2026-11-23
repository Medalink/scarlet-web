@php($l = $d['levelling'])
<section id="levelling" class="sc-sec">
  <h2>Levelling</h2>
  <div class="mt-6 grid grid-cols-[minmax(0,1fr)] gap-4 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
    <div class="sc-panel px-5 py-4" style="--surface:var(--panel)">
      <div class="flex flex-wrap items-baseline gap-x-4"><h3 class="sc-h">Hours on each level</h3><div class="sc-key ml-auto"><span style="--c:var(--accent)">{{ $d['head']['first'] }}</span>@if ($l['ruleset'])<span style="--c:var(--text)">Median on {{ $l['ruleset'] }}</span>@endif</div></div>
      <div class="mt-3" data-h="190" data-cols="{{ json_encode($l['cols'], JSON_THROW_ON_ERROR) }}"></div>
      @if ($l['sentence'])<p class="sc-say mt-3" style="font-size:15.5px">{{ $l['sentence'] }}</p>@endif
    </div>
    <div class="sc-panel px-5 py-4">
      @if ($l['xp'] !== [])
        <h3 class="sc-h">Experience by source</h3>
        <x-sc.stack class="mt-3" :rows="$l['xp']" />
      @endif
      @if ($l['last'] !== [])
        <p class="sc-label mb-1 {{ $l['xp'] === [] ? '' : 'mt-5' }}">Time on the last three levels</p>
        <x-sc.bars :rows="$l['last']" />
      @endif
    </div>
  </div>
</section>
