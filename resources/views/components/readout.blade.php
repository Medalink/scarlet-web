{{-- The line under a chart. line: [bold lead, rest]. A chart whose spec `out` is this id rewrites it as the reader moves. --}}
@props(['line', 'id' => null])
<p class="readout"@if ($id) id="{{ $id }}" aria-live="polite"@endif><b>{{ $line[0] }}</b>{{ $line[1] ?? '' }}</p>
