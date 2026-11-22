{{-- One whole split into parts: a stack bar, then each part's count and share. parts: AdminCharts::split(). --}}
@props(['parts', 'label'])
<x-stack-bar :label="$label" :segments="array_map(fn (array $part): array => ['class' => $part['class'], 'count' => $part['count'], 'say' => $part['label'].': '.$part['value']], $parts)" />
<dl class="pair parts" style="grid-template-columns:repeat({{ count($parts) }},minmax(0,1fr))">@foreach ($parts as $part)<div><dt><i class="{{ $part['class'] }}"></i>{{ $part['label'] }}</dt><dd>{{ $part['value'] }}<small>{{ $part['share'] }}</small></dd></div>@endforeach</dl>
