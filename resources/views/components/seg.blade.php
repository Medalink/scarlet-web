{{-- A segmented control. items: [['label' => 'All', 'href' => '/stats', 'on' => true]]. Links by default, toggle buttons with `buttons`. --}}
@props(['label', 'items', 'buttons' => false])
<div class="seg" role="group" aria-label="{{ $label }}">@foreach ($items as $item)@if ($buttons)<button type="button" aria-pressed="{{ empty($item['on']) ? 'false' : 'true' }}">{{ $item['label'] }}</button>@else<a href="{{ $item['href'] ?? '#' }}"@if (! empty($item['on'])) aria-current="true"@endif>{{ $item['label'] }}</a>@endif@endforeach</div>
