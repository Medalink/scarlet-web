@php($i = $d['imported'])
<section id="imported" class="sc-sec">
  <div class="sc-sec-head"><h2>Imported from {{ $i['source'] }}</h2><p class="ml-auto pb-1 text-[14px] text-muted">Only you</p></div>
  <p class="sc-note mt-3">{{ $i['range'] }}. Scarlet did not record this history itself, so no board or other page counts it.</p>
  <x-sc.tiles class="mt-5" :cols="min(7, count($i['figures']))">
    @foreach ($i['figures'] as $figure)<x-sc.tile :label="$figure['label']" :value="$figure['value']" />@endforeach
  </x-sc.tiles>
  <div class="mt-4 grid grid-cols-[minmax(0,1fr)] gap-4 lg:grid-cols-2">
    @foreach (['days' => 'Sales and purchases by day', 'items' => 'Sale prices by item'] as $key => $title)
      @if ($i[$key])
        <div class="sc-panel max-h-[420px] overflow-auto px-5 py-4">
          <h3 class="sc-h">{{ $title }}</h3>
          <table class="mt-3 w-full text-[13.5px]">
            <thead><tr>@foreach ($i[$key]['columns'] as $column)<th class="pb-1.5 pr-3 text-[12px] font-normal text-muted {{ ($column['numeric'] ?? false) ? 'text-right' : 'text-left' }}">{{ $column['label'] }}</th>@endforeach</tr></thead>
            <tbody>@foreach ($i[$key]['rows'] as $row)<tr class="border-t border-line">@foreach ($row['cells'] as $n => $cell)<td class="py-1.5 pr-3 {{ ($i[$key]['columns'][$n]['numeric'] ?? false) ? 'text-right' : '' }}">{{ $cell }}</td>@endforeach</tr>@endforeach</tbody>
          </table>
        </div>
      @endif
    @endforeach
  </div>
</section>
