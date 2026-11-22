<div class="scroll-x"><table class="data" style="margin-top:1.1rem">
  <thead><tr><th>The recipe was</th><th class="n">Crafts</th><th class="n">Gave a skill point</th></tr></thead>
  <tbody>@foreach ($m['rows'] as $row)
    <tr><td>{{ $row['label'] }}</td><td class="n">{{ $row['crafts'] }}</td><td class="n">{{ $row['share'] }}</td></tr>@endforeach
  </tbody></table></div>
