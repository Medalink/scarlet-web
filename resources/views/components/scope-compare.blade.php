{{-- The figures of everyone beside those of one character. compare: the answer of App\View\Pages\ScopeCompare::figures(). --}}
@props(['compare', 'heading' => 'You vs everyone'])
@if ($compare['rows'] !== [])
<section class="scope-compare" aria-label="{{ $heading }}">
  <div class="scroll-x"><table class="data"><thead><tr><th>{{ $heading }}</th><th class="n">Everyone</th><th class="n">{{ $compare['name'] }}</th></tr></thead>
  <tbody>@foreach ($compare['rows'] as [$label, $everyone, $yours])<tr><td>{{ $label }}</td><td class="n">{{ $everyone }}</td><td class="n"><b>{{ $yours }}</b></td></tr>@endforeach</tbody></table></div>
</section>
@endif
