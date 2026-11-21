{{-- A data table. columns: [['label', 'numeric' => true, 'class' => 'rk']]. rows: [['class' => 'me', 'cells' => [cell, ...]]].
     A cell is a string or ['text', 'href', 'class', 'tag' => 'HC', 'bar' => '5.2rem', 'dot' => true or a colour]. --}}
@props(['columns', 'rows', 'style' => null])
<div class="scroll-x"><table class="data"@if ($style) style="{{ $style }}"@endif>
<thead><tr>@foreach ($columns as $column)<th @class([($column['numeric'] ?? false) ? 'n' : null])>@if (! empty($column['hide']))<span class="vh">{{ $column['label'] }}</span>@else{{ $column['label'] ?? '' }}@endif</th>@endforeach</tr></thead>
<tbody>
@foreach ($rows as $row)
<tr @class([$row['class'] ?? null])>@foreach ($row['cells'] as $i => $cell)@php($cell = is_array($cell) ? $cell : ['text' => $cell])<td @class([($columns[$i]['numeric'] ?? false) ? 'n' : null, $columns[$i]['class'] ?? null])>@if (! empty($cell['dot']))<i class="dotc"@if (is_string($cell['dot'])) style="background:{{ $cell['dot'] }}"@endif aria-hidden="true"></i>@endif@if (isset($cell['bar']))<i class="bar" style="width:{{ $cell['bar'] }}"></i>@endif@if (! empty($cell['href']))<a @class([$cell['class'] ?? null]) href="{{ $cell['href'] }}">{{ $cell['text'] }}</a>@else{{ $cell['text'] }}@endif@if (! empty($cell['tag']))<span class="tag hc">{{ $cell['tag'] }}</span>@endif</td>@endforeach</tr>
@endforeach
</tbody></table></div>
