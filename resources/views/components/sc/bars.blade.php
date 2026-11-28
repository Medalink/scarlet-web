@props(['rows', 'of' => null])
@php($max = $of ?? max(1, ...array_map(fn (array $row): float => (float) $row[1], $rows)))
<div {{ $attributes->class('sc-bars') }}>
  @foreach ($rows as $row)
    <div class="row"><span>{{ $row[0] }}</span><b>{{ $row[2] ?? number_format($row[1]) }}</b><i style="--w:{{ round($row[1] / $max * 100, 1) }}%@isset($row[3]);--c:var({{ $row[3] }})@endisset"></i></div>
  @endforeach
</div>
