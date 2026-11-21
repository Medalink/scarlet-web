{{-- Play time by weekday and hour. spec: kind hours, days ['Mon', ...], levels [7 rows of 24 levels from 0 to 4], say [[bold, rest]] per cell, day first. --}}
@props(['id', 'label', 'spec'])
<div class="hours" id="{{ $id }}" role="group" aria-label="{{ $label }}" data-chart="{{ json_encode($spec) }}"></div>
