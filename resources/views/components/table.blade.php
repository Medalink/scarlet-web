{{-- A data table. columns: [['label', 'numeric' => true, 'class' => 'rk', 'local' => the label's local wording for
     resources/js/local-time.js]]. rows: [['class' => 'me', 'cells' => [cell, ...]]].
     A cell is a string or ['text', 'href', 'class', 'tag' => 'HC', 'bar' => '5.2rem', 'dot' => true or a colour, 'characterClass' => 'WARLOCK' to colour the dot,
     'date' => a date from the Dates helper, drawn as it is,
     'rate' => an App\Support\RateEstimate, drawn by x-rate (`'counts' => true` adds its counts), 'icon' => an item icon address, or null for an empty square, drawn before the text,
     'plainTag' => true draws the tag without the red of 'tag',
     'note' => muted words after the text, 'code' => true sets the text as code,
     'stack' => [['class' => 'c-s1', 'count']] with 'width' => '5rem', a bar split into parts drawn before the text].
     A row of ['hair' => true] is a hairline across the table.
     A row with 'detail' => ['summary', 'columns', 'rows'] is followed by a row holding a collapsed table of its own. --}}
@props(['columns', 'rows', 'style' => null])
<div class="scroll-x"><table class="data"@if ($style) style="{{ $style }}"@endif>
<thead><tr>@foreach ($columns as $column)<th @class([($column['numeric'] ?? false) ? 'n' : null])@if (! empty($column['local'])) data-local-label="{{ $column['local'] }}" @endif>@if (! empty($column['hide']))<span class="vh">{{ $column['label'] }}</span>@else{{ $column['label'] ?? '' }}@endif</th>@endforeach</tr></thead>
<tbody>
@foreach ($rows as $row)
@if (! empty($row['hair']))
<tr class="hair"><td colspan="{{ count($columns) }}"></td></tr>
@continue
@endif
<tr @class([$row['class'] ?? null])>@foreach ($row['cells'] as $i => $cell)@php($cell = is_array($cell) ? $cell : ['text' => $cell])<td @class([($columns[$i]['numeric'] ?? false) ? 'n' : null, $columns[$i]['class'] ?? null])@if (! empty($cell['characterClass'])) data-class="{{ $cell['characterClass'] }}"@endif>@if (! empty($cell['dot']))<i class="dotc"@if (is_string($cell['dot'])) style="background:{{ $cell['dot'] }}"@endif aria-hidden="true"></i>@endif@if (isset($cell['bar']))<i class="bar" style="width:{{ $cell['bar'] }}"></i>@endif@if (isset($cell['stack']))<span class="stack" style="width:{{ $cell['width'] }}" aria-hidden="true">@foreach ($cell['stack'] as $part)<i class="{{ $part['class'] }}" style="flex-grow:{{ $part['count'] }}"></i>@endforeach</span>@endif@if (array_key_exists('icon', $cell))<x-item-icon :icon="$cell['icon']" :any="true" />@endif@if (isset($cell['rate']))<x-rate :estimate="$cell['rate']" :counts="! empty($cell['counts'])" />@elseif (isset($cell['date'])){{ $cell['date'] }}@elseif (! empty($cell['href']))<a @class([$cell['class'] ?? null]) href="{{ $cell['href'] }}">{{ $cell['text'] }}</a>@elseif (! empty($cell['code']))<code>{{ $cell['text'] }}</code>@else{{ $cell['text'] }}@endif@if (! empty($cell['tag']))<span @class(['tag', 'hc' => empty($cell['plainTag'])])>{{ $cell['tag'] }}</span>@endif@if (! empty($cell['note']))<span class="mut"> {{ $cell['note'] }}</span>@endif</td>@endforeach</tr>
@if (! empty($row['detail']))
<tr class="sub"><td colspan="{{ count($columns) }}"><details><summary>{{ $row['detail']['summary'] }}</summary><x-table :columns="$row['detail']['columns']" :rows="$row['detail']['rows']" /></details></td></tr>
@endif
@endforeach
</tbody></table></div>
