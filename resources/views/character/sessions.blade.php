@php($s = $d['sessions'])
<section id="sessions" class="sc-sec grid grid-cols-[minmax(0,1fr)] gap-10 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
  <div>
    <h2>Sessions</h2>
    @if ($s['latest'])<x-sc.session-card class="mt-6" :recap="$s['latest']['recap']" :href="$s['latest']['href']" />@endif
    @if ($s['rows'] !== [])
      <div class="mt-3">
        @foreach ($s['rows'] as $row)
          <a href="{{ $row['href'] }}" class="sc-sess"><time class="sc-mono" datetime="{{ $row['at'] }}">{{ $row['day'] }}</time><b>{{ $row['title'] }}</b><span>{{ $row['played'] }}</span><span>{{ $row['kills'] }}</span><span class="text-right">{{ $row['level'] }}</span></a>
        @endforeach
      </div>
    @endif
    @if ($s['more'] || $s['total'])
      <p class="mt-4">@if ($s['more'])<a href="{{ $s['more'] }}" class="sc-btn quiet small">Show 20 more</a>@endif @if ($s['total'])<span class="sc-label ml-2">{{ $s['total'] }}</span>@endif</p>
    @endif
  </div>
  <div class="lg:pt-[70px]">
    <p class="sc-h3">Days played, last 16 weeks</p>
    <div class="sc-days mt-3" aria-label="Days played, last 16 weeks">
      @foreach ($s['days']['cells'] as $cell)
        @if ($cell === null)<i class="none"></i>@else<i data-v="{{ $cell['level'] }}" data-tip="{{ $cell['tip'] }}"></i>@endif
      @endforeach
    </div>
    <p class="sc-say mt-4" style="font-size:15.5px">Played on {{ $s['days']['played'] }} {{ $s['days']['played'] === 1 ? 'day' : 'days' }}.@if ($s['days']['longest'] > 1) The longest run is <b>{{ $s['days']['longest'] }} days in a row</b>.@endif</p>
  </div>
</section>
