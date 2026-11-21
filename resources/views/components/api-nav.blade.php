{{-- The reference's side list. groups: [['title' => 'Basics', 'links' => [['href' => '#use', 'label' => 'Using the API']]]]. --}}
@props(['groups'])
<nav class="ref-nav" aria-label="Endpoints">@foreach ($groups as $group)<div><h3>{{ $group['title'] }}</h3>@foreach ($group['links'] as $link)<a href="{{ $link['href'] }}">{{ $link['label'] }}</a>@endforeach</div>@endforeach</nav>
