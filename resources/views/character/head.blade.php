<section class="grid grid-cols-[minmax(0,1fr)] items-start gap-8 lg:grid-cols-[minmax(0,1fr)_470px]">
  <div>
    <h1 class="sc-name">{{ $head['name'] }}</h1>
    <p class="mt-3 text-[16px]">{{ $head['line'] }}@if ($d['claim']) · <a href="/download">Is this yours? Link the app to manage it</a>@endif @if ($d['asVisitor'] && $d['owner'] === false) · <a href="{{ $d['path'] }}">Back to your view</a>@endif</p>
    @if ($head['rule'])
      <div class="sc-rule60" style="--at:{{ $head['rule']['at'] }}%" aria-hidden="true"><i></i><u></u>@foreach ($head['rule']['ticks'] as $tick)<span style="left:{{ $tick['at'] }}%">{{ $tick['n'] }}</span>@endforeach</div>
    @endif
    <x-sc.tiles class="mt-7" :cols="3">
      @foreach ($head['tiles'] as $tile)
        <x-sc.tile :label="$tile['label']" :value="$tile['value']" :unit="$tile['unit'] ?? null" :sub="$tile['sub']" />
      @endforeach
    </x-sc.tiles>
  </div>
  @if ($head['route'])
    <div class="sc-panel relative overflow-hidden" style="height:372px">
      <div data-route="{{ json_encode($head['route'], JSON_THROW_ON_ERROR) }}" data-h="370" style="height:370px"></div>
      <a href="{{ $head['map'] }}" class="sc-btn quiet small absolute right-3 top-3" style="background:var(--panel)">Open the map</a>
    </div>
  @endif
</section>
