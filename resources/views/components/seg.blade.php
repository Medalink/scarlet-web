{{-- A segmented control. items: [['label' => 'All', 'href' => '/stats', 'on' => true, 'data' => ['map-layer' => 'paths']]]. Links by default, toggle buttons with `buttons`;
     an item's `data` becomes data attributes of its button. --}}
@props(['label', 'items', 'buttons' => false])
<div class="seg" role="group" aria-label="{{ $label }}">@foreach ($items as $item)@if ($buttons)<button type="button" aria-pressed="{{ empty($item['on']) ? 'false' : 'true' }}"@foreach ($item['data'] ?? [] as $name => $value) data-{{ $name }}="{{ $value }}"@endforeach>{{ $item['label'] }}</button>@else<a href="{{ $item['href'] ?? '#' }}"@if (! empty($item['on'])) aria-current="true"@endif>{{ $item['label'] }}</a>@endif@endforeach</div>
