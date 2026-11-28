{{-- The one heatmap of a week: weekday rows, 12-hour ticks, the busiest hour line and the Quiet to Busiest key. spec: Charts::weekCells with out "hours-out"; line: the busiest hour in UTC for the first paint. --}}
@props(['spec', 'line' => null, 'label' => 'Play time'])
<x-hours-grid id="hours" :label="$label.' by weekday and hour of day, in UTC'" :spec="$spec" />
<x-readout id="hours-out" :line="$line ?? ['', '']" />
<x-heat-key low="Quiet" high="Busiest" :zero="true" extra="Hours are UTC" extra-local="" />
