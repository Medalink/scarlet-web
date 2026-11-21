<x-module :span="$span" :first="$first" :last="$last" :level="$level" id="h-creatures" heading="Rare and elite creatures met">
  <dl class="met">@foreach ($d['creatures']['figures'] as $figure)<div><dt>{{ $figure['label'] }}</dt><dd>{{ $figure['value'] }}</dd></div>@endforeach</dl>
  @if ($d['creatures']['rows'])<x-rank :rows="$d['creatures']['rows']" />@endif
</x-module>
