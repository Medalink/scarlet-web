{{-- A chart key. items: [['label' => 'Median', 'class' => 'k-line', 'style' => 'background:var(--accent)']]. --}}
@props(['items'])
<ul class="key">@foreach ($items as $item)<li><i @class([$item['class'] ?? null])@if (! empty($item['style'])) style="{{ $item['style'] }}"@endif></i>{{ $item['label'] }}</li>@endforeach</ul>
