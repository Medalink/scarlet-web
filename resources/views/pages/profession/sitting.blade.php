<dl class="pair" style="grid-template-columns:repeat(2,minmax(0,1fr))">
  <div><dt>Median length</dt><dd>{{ $m['length'] }}<small>crafts no more than two minutes apart, over the last 90 days</small></dd></div>
  <div><dt>Median crafts</dt><dd>{{ $m['crafts'] }}<small>over the last 90 days</small></dd></div>
</dl>
<dl class="pair" style="grid-template-columns:repeat(2,minmax(0,1fr));margin-top:0.9rem">
  <div><dt>Sittings</dt><dd>{{ $m['sittings'] }}<small>all time</small></dd></div>
  <div><dt>Seconds between crafts</dt><dd>{{ $m['gap'] }}<small>median within a sitting, all time</small></dd></div>
</dl>
