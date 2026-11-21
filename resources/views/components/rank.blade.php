{{-- A ranked list with bars. rows: [['label', 'value', 'share' => 0 to 100, 'href', 'class' => bar class, 'quality' => 'q2']]. --}}
@props(['rows', 'plain' => false])
<ol @class(['rank', 'plain' => $plain])>@foreach ($rows as $row)<li>@if (! empty($row['href']))<a @class(['who', $row['quality'] ?? null]) href="{{ $row['href'] }}">{{ $row['label'] }}</a>@else<span class="who">{{ $row['label'] }}</span>@endif<span class="val">{{ $row['value'] }}</span><span class="track"><i @class([$row['class'] ?? null]) style="width:{{ round($row['share'], 1) }}%"></i></span></li>@endforeach</ol>
