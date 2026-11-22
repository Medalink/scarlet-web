<dl class="pair" style="grid-template-columns:minmax(0,1fr)">
  <div><dt>Median length</dt><dd>{{ $m['length'] }}<small>{{ $m['note'] }}</small></dd></div>
</dl>
<dl class="pair" style="grid-template-columns:repeat(2,minmax(0,1fr));margin-top:0.9rem">
  <div><dt>Median {{ $w['attempts'] }}</dt><dd>{{ $m['attempts'] ?? '–' }}</dd></div>
  <div><dt>Trips</dt><dd>{{ $m['trips'] }}</dd></div>
</dl>
