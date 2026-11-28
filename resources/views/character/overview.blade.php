@php
    $o = $d['overview'];
    $compare = $o['compare'];
    $curve = $o['curve'];
@endphp
<section id="overview" @class(['mt-10 grid grid-cols-[minmax(0,1fr)] gap-10', 'lg:grid-cols-[minmax(0,1.25fr)_minmax(0,1fr)]' => ! $compare['empty']]) style="--surface:var(--bg)">
  @unless ($compare['empty'])
    <div>
      <h2 class="sc-h" style="font-size:30px">{{ $compare['heading'] }}</h2>
      @if ($compare['key'])
        <div class="sc-key mt-3"><span style="--c:var(--accent)">{{ $compare['name'] }}</span><span style="--c:var(--s-0)">{{ $compare['key'] }}</span><span style="--c:var(--text)">Median</span></div>
      @endif
      @if ($compare['rows'] !== [])<x-sc.compare class="mt-4" :rows="$compare['rows']" :n="$compare['n']" :label="150" />@endif
      @if ($compare['rows'] !== [] && $compare['classRows'] !== [])<hr class="sc-rule my-4">@endif
      @if ($compare['classRows'] !== [])<x-sc.compare class="{{ $compare['rows'] === [] ? 'mt-4' : '' }}" :rows="$compare['classRows']" :n="$compare['n']" :label="150" />@endif
      @if ($compare['sentence'])<p class="sc-say mt-5">{{ $compare['sentence'] }}</p>@endif
    </div>
  @endunless
  <div>
    @if ($o['standings'] !== [])
      <h2 class="sc-h" style="font-size:30px">Standings</h2>
      <div class="mt-2">
        @foreach ($o['standings'] as $row)
          <a class="sc-rank" href="{{ $row['href'] }}"><span class="n">{{ $row['place'] }}</span><span>{{ $row['title'] }}</span><span class="sc-mono">{{ $row['value'] }}</span></a>
        @endforeach
      </div>
    @endif
    @if ($curve)
      <h2 class="sc-h {{ $o['standings'] === [] ? '' : 'mt-9' }}" style="font-size:30px">Hours played to reach each level</h2>
      <div class="sc-key mt-3"><span style="--c:var(--accent)">{{ $head['first'] }}</span><span style="--c:var(--muted)">{{ $curve['spec']['medianName'] }}</span><span style="--c:rgba(255,255,255,0.14)">Middle half</span></div>
      <div class="mt-3" data-h="210" data-line="{{ json_encode($curve['spec'], JSON_THROW_ON_ERROR) }}"></div>
      <p class="sc-say mt-3" style="font-size:15.5px">{!! $curve['sentence'] !!}</p>
    @endif
  </div>
</section>
