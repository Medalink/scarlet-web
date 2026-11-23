@php($l = $d['loot'])
<section id="loot" class="sc-sec">
  <h2>Loot and professions</h2>
  <div class="mt-6 grid grid-cols-[minmax(0,1fr)] gap-4 lg:grid-cols-3">
    <div class="sc-panel px-5 py-4">
      <h3 class="sc-h">Items looted</h3>
      @if ($l['stack'] !== [])
        <x-sc.stack class="mt-3" :rows="$l['stack']" />
        <p class="mt-4 text-[13.5px]">{{ $l['line'] }}@if ($l['best']) · best drop <a class="font-bold" href="/items/{{ $l['best']['id'] }}">{{ $l['best']['name'] }}</a>@endif</p>
      @else
        <p class="sc-note mt-3">No loot recorded yet.</p>
      @endif
    </div>
    <div class="sc-panel px-5 py-4"><h3 class="sc-h">Professions</h3>@if ($l['professions'] !== [])<x-sc.bars class="mt-3" :rows="$l['professions']" />@else<p class="sc-note mt-3">No professions learned.</p>@endif</div>
    <div class="sc-panel px-5 py-4"><h3 class="sc-h">Best drops</h3>@if ($l['drops'] !== [])<x-sc.bars class="mt-3" :rows="$l['drops']" />@else<p class="sc-note mt-3">Nothing worth listing yet.</p>@endif</div>
  </div>
  @if ($l['gold'])
    <div class="sc-panel mt-4 px-5 py-4">
      <div class="flex flex-wrap items-baseline gap-x-4"><h3 class="sc-h">Gold held</h3><p class="ml-auto text-[13.5px]">{{ $l['gold']['held'] }} · {{ $l['gold']['change'] }}</p></div>
      <x-sc.area class="mt-3" :spec="$l['gold']['spec']" :height="160" />
    </div>
  @endif
</section>
