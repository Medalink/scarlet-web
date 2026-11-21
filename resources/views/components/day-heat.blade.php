{{-- Play time by day, a column per week from Monday. spec: kind days, days [[level 0 to 4, hidden]], months one label per week,
     say [[bold, rest]] per day, out (the id of its readout). --}}
@props(['id', 'spec'])
<div class="heat" id="{{ $id }}" data-chart="{{ json_encode($spec) }}"></div>
