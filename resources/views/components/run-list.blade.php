{{-- Dungeon runs, one row each. rows: [['label', 'date', 'time', 'share' => 0 to 100, 'bosses', 'deaths', 'crosses', 'deathsSay', 'group']]. --}}
@props(['rows'])
<ol class="runs">@foreach ($rows as $row)<li>
  <span class="who">{{ $row['label'] }}</span><span class="date">{{ $row['date'] }}</span>
  <span class="track"><i style="width:{{ round($row['share'], 1) }}%"></i></span><span class="val">{{ $row['time'] }}</span>
  <span class="bosses">{{ $row['bosses'] }}</span>
  <span class="dead" title="{{ $row['deathsSay'] }}"><span class="vh">{{ $row['deathsSay'] }}</span>@for ($n = 0; $n < $row['crosses']; $n++)<svg viewBox="0 0 14 14" aria-hidden="true"><path d="M2 2l10 10M12 2L2 12"/></svg>@endfor @if ($row['deaths'] > $row['crosses'])<small aria-hidden="true">{{ $row['deaths'] }}</small>@endif</span>
  <span class="size">{{ $row['group'] }}</span>
</li>@endforeach</ol>
