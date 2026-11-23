@props(['rows', 'key' => true, 'height' => 12])
@php
    $rows = array_map(fn (array $row): array => ['label' => $row[0], 'value' => $row[1], 'var' => $row[2], 'text' => $row[3] ?? number_format($row[1])], $rows);
    $sum = max(1, array_sum(array_column($rows, 'value')));
@endphp
<div {{ $attributes }}>
  <div class="sc-stack" style="height:{{ $height }}px">
    @foreach ($rows as $row)<i style="flex:{{ $row['value'] }};--c:var({{ $row['var'] }})" data-tip="{{ $row['label'] }}|{{ $row['text'] }} · {{ round($row['value'] / $sum * 100) }}%"></i>@endforeach
  </div>
  @if ($key)
    <div class="sc-key mt-[9px]">
      @foreach ($rows as $row)<span style="--c:var({{ $row['var'] }})">{{ $row['label'] }} <b>{{ $row['text'] }}</b></span>@endforeach
    </div>
  @endif
</div>
