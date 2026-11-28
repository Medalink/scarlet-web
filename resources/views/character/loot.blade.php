@php
$l = $d['loot'];
$panels = array_filter([
    'looted' => $l['stack'] !== [],
    'professions' => $l['professions'] !== [],
    'drops' => $l['drops'] !== [],
]);
$columns = [1 => 'lg:grid-cols-1', 2 => 'lg:grid-cols-2', 3 => 'lg:grid-cols-3'][count($panels)] ?? '';
@endphp
<section id="loot" class="sc-sec">
  <h2>Loot and professions</h2>
  @if ($panels !== [])
  <div class="mt-6 grid grid-cols-[minmax(0,1fr)] gap-4 {{ $columns }}">
    @if (isset($panels['looted']))
    <div class="sc-panel px-5 py-4">
      <h3 class="sc-h">Items looted</h3>
      <x-sc.stack class="mt-3" :rows="$l['stack']" />
      <p class="mt-4 text-[13.5px]">{{ $l['line'] }}@if ($l['best']) · best drop <a class="font-bold" href="/items/{{ $l['best']['id'] }}">{{ $l['best']['name'] }}</a>@endif</p>
    </div>
    @endif
    @if (isset($panels['professions']))
    <div class="sc-panel px-5 py-4"><h3 class="sc-h">Professions</h3><x-sc.bars class="mt-3" :rows="$l['professions']" /></div>
    @endif
    @if (isset($panels['drops']))
    <div class="sc-panel px-5 py-4"><h3 class="sc-h">Best drops</h3><x-sc.bars class="mt-3" :rows="$l['drops']" /></div>
    @endif
  </div>
  @endif
  @if ($l['gold'])
    <div class="sc-panel mt-4 px-5 py-4">
      <div class="flex flex-wrap items-baseline gap-x-4"><h3 class="sc-h">Gold held</h3><p class="ml-auto text-[13.5px]">{{ $l['gold']['held'] }} · {{ $l['gold']['change'] }}</p></div>
      <x-sc.area class="mt-3" :spec="$l['gold']['spec']" :height="160" />
    </div>
  @endif
</section>
