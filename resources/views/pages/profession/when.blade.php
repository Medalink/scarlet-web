<x-hours-grid id="hours" :label="ucfirst($w['attempts']).' by weekday and hour of day, in UTC'" :spec="$m['spec']" />
<x-readout id="hours-out" :line="$d['hoursSummary'] ?? ['', '']" />
<x-heat-key low="Quiet" high="Busiest" :zero="true" extra="Hours are UTC" extra-local="" />
