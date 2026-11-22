<x-rank :rows="$m['ends']" />
@if ($m['lures'])
<h2 id="h-lures" style="margin-top:2rem">What is on the pole</h2>
<x-rank :rows="$m['lures']" plain />
@endif
