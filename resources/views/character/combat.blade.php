@php($c = $d['combat'])
<section id="combat" class="sc-sec">
  <h2>Combat</h2>
  <div class="mt-6 grid grid-cols-[minmax(0,1fr)] gap-4 lg:grid-cols-3">
    <div class="sc-panel px-5 py-4"><h3 class="sc-h">Killed most</h3>@if ($c['killed'] !== [])<x-sc.bars class="mt-3" :rows="$c['killed']" />@else<p class="sc-note mt-3">No kills recorded yet.</p>@endif</div>
    <div class="sc-panel px-5 py-4">
      <h3 class="sc-h">{{ $c['diedTitle'] }}</h3>
      @if ($c['died'] !== [])<x-sc.bars class="mt-3" :rows="$c['died']" />@else<p class="sc-note mt-3">No deaths recorded.</p>@endif
      @if ($c['deathLine'])<p class="mt-4 text-[13.5px]">{{ $c['deathLine'] }}</p>@endif
    </div>
    <div class="sc-panel px-5 py-4">
      <h3 class="sc-h">Dungeons</h3>
      @if ($c['dungeons'] !== [])<x-sc.bars class="mt-3" :rows="$c['dungeons']" />@else<p class="sc-note mt-3">No dungeon runs recorded.</p>@endif
      @if ($c['dungeonLine'])<p class="mt-4 text-[13.5px]">{{ $c['dungeonLine'] }}</p>@endif
    </div>
  </div>
  @if ($d['battlegrounds'] !== [])
    <h3 class="sc-h mt-8" id="battlegrounds" style="font-size:24px">Battlegrounds</h3>
    <div class="mt-4 grid grid-cols-[minmax(0,1fr)] gap-4 lg:grid-cols-2">
      @foreach ($d['battlegrounds'] as $match)
        <div class="sc-panel overflow-x-auto px-5 py-4">
          <p class="sc-h3">{{ $match['heading'] }}</p>
          <p class="sc-mono mt-1"><time datetime="{{ $match['instant'] }}" data-local="j M Y H:i">{{ $match['at'] }}</time></p>
          <table class="mt-3 w-full text-[13.5px]">
            @foreach ($match['rows'] as $row)
              <tr class="border-t border-line">@foreach ($row as $cell)<td class="py-1.5 pr-3">{{ is_array($cell) ? ($cell['text'] ?? '') : $cell }}</td>@endforeach</tr>
            @endforeach
          </table>
        </div>
      @endforeach
    </div>
  @endif
</section>
