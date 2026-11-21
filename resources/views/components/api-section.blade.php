{{-- One block of the API reference. With `method` the heading is an endpoint; params: [['name', 'meaning']] makes the parameter table;
     the slot holds the prose and examples, as x-json blocks. --}}
@props(['id', 'title', 'method' => null, 'summary' => null, 'params' => []])
<section class="ep" id="{{ $id }}"><h2>@if ($method)<b>{{ $method }}</b>@endif{{ $title }}</h2>@if ($summary)<p>{{ $summary }}</p>@endif@if ($params !== [])<table class="data"><thead><tr><th>Parameter</th><th>Meaning</th></tr></thead><tbody>@foreach ($params as [$name, $meaning])<tr><td><code>{{ $name }}</code></td><td>{{ $meaning }}</td></tr>@endforeach</tbody></table>@endif{{ $slot }}</section>
