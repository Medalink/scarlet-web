<div class="scroll-x"><table class="data catches" style="margin-top:1.1rem">
  <thead><tr><th>Recipe</th><th>Makes</th><th class="n">Crafts</th><th class="n">Made</th><th class="n">Characters</th><th class="n">Median skill</th></tr></thead>
  <tbody>@foreach ($m['rows'] as $row)
    <tr><td><a href="{{ $row['href'] }}">{{ $row['label'] }}</a>@if ($row['uses'])<small class="uses">{{ $row['uses'] }}</small>@endif</td><td>@if ($row['makes'])<a href="{{ $row['makesHref'] }}">{{ $row['makes'] }}</a>@else–@endif</td><td class="n"><i class="bar" style="width:{{ $row['bar'] }}"></i>{{ $row['crafts'] }}</td><td class="n">{{ $row['made'] }}</td><td class="n">{{ $row['characters'] }}</td><td class="n">{{ $row['skill'] }}</td></tr>@endforeach
  </tbody></table></div>
@if ($m['note'])<x-note>{{ $m['note'] }}</x-note>@endif
