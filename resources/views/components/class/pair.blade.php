{{-- Label and number pairs: block is App\Support\ClassPage\Pair. --}}
@props(['block', 'icons'])
<dl class="pair">@foreach ($block->rows as $row)<div><dt>{{ $row->label }}</dt><dd>{{ $row->unit->figure($row->value) }}@if ($row->small)<small>{{ $row->small }}</small>@endif</dd></div>@endforeach</dl>
