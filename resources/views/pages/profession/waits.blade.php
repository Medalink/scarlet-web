<x-plot id="wait" :label="ucfirst($w['results']).' by the seconds they took'" :spec="$m['spec']" />
@if ($m['note'])<x-note>{{ $m['note'] }}</x-note>@endif
