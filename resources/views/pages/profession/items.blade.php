<div class="scroll-x"><table class="data catches" style="margin-top:1.1rem">
  <thead><tr><th>{{ $w['item'] }}</th><th class="n">Of {{ $w['results'] }}</th><th class="n">Median skill</th><th>Most common in</th></tr></thead>
  <tbody>@foreach ($m['rows'] as $row)
    <tr><td><a class="buff" href="{{ $row['href'] }}">@if ($row['icon'])<img src="{{ $row['icon'] }}" alt="" width="26" height="26" loading="lazy">@elseif ($m['icons'])<i class="buff-none" aria-hidden="true"></i>@endif{{ $row['label'] }}</a></td><td class="n"><i class="bar" style="width:{{ $row['bar'] }}"></i>{{ $row['share'] }}</td><td class="n">{{ $row['skill'] }}</td><td>@if ($row['zone'])<a href="{{ $row['zoneHref'] }}">{{ $row['zone'] }}</a>@else–@endif</td></tr>@endforeach
  </tbody></table></div>
<x-note>{{ $m['note'] }}</x-note>
