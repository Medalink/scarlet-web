{{-- The levelling ridge with a flag at each level where characters stand. spec: App\View\Pages\StatsGame Ascent, every coordinate computed there. --}}
@props(['spec'])
<div class="ascent scroll-x"><svg viewBox="{{ $spec['viewBox'] }}" role="img" aria-label="{{ $spec['label'] }}">
<defs><linearGradient id="ascent-land" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ff2d55" stop-opacity=".2"/><stop offset="1" stop-color="#ff2d55" stop-opacity="0"/></linearGradient></defs>
@foreach ($spec['grid'] as $line)<line class="grid" x1="{{ $spec['left'] }}" x2="{{ $spec['right'] }}" y1="{{ $line['y'] }}" y2="{{ $line['y'] }}"/><text x="{{ $spec['left'] - 8 }}" y="{{ $line['ty'] }}" text-anchor="end">{{ $line['text'] }}</text>@endforeach
<line class="axis" x1="{{ $spec['left'] }}" x2="{{ $spec['right'] }}" y1="{{ $spec['base'] }}" y2="{{ $spec['base'] }}"/>
@foreach ($spec['ticks'] as $tick)<line class="axis" x1="{{ $tick['x'] }}" x2="{{ $tick['x'] }}" y1="{{ $spec['base'] }}" y2="{{ $tick['y2'] }}"/><text x="{{ $tick['x'] }}" y="{{ $tick['ty'] }}" text-anchor="{{ $tick['anchor'] }}"@if ($tick['dx']) dx="{{ $tick['dx'] }}"@endif>{{ $tick['text'] }}</text>@endforeach
<path class="land" d="{{ $spec['land'] }}"/><path class="ridge" d="{{ $spec['ridge'] }}"/>
@if ($spec['beyond'])<path class="beyond" d="{{ $spec['beyond']['path'] }}"/><circle class="foot" cx="{{ $spec['beyond']['cx'] }}" cy="{{ $spec['beyond']['cy'] }}" r="3.2" style="stroke:var(--muted)"/><text class="cap" x="{{ $spec['beyond']['tx'] }}" y="{{ $spec['beyond']['ty'] }}" text-anchor="end">{{ $spec['beyond']['text'] }}</text>@endif
@foreach ($spec['flags'] as $flag)<g @class(['flag', 'summit' => $flag['summit']])><title>{{ $flag['title'] }}</title><line class="staff" x1="{{ $flag['x'] }}" x2="{{ $flag['x'] }}" y1="{{ $flag['y'] }}" y2="{{ $flag['top'] }}"/><path class="pennant" d="{{ $flag['pennant'] }}"/><circle class="foot" cx="{{ $flag['x'] }}" cy="{{ $flag['y'] }}" r="3.2"/><text class="n" x="{{ $flag['tx'] }}" y="{{ $flag['ty'] }}">{{ $flag['count'] }}</text></g>@endforeach
</svg></div>
