{{-- The character page's figure strip: a title with its date range, then the figures. --}}
@props(['title', 'range', 'items'])
<section class="week" aria-labelledby="h-week">
  <div><h2 id="h-week">{{ $title }}</h2><p>{{ $range }}</p></div>
  @foreach ($items as $item)<div><dt>{{ $item['label'] }}</dt><dd>{{ $item['value'] }}</dd></div>@endforeach
</section>
