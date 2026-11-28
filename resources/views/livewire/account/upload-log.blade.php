<div wire:poll.30s>
@if ($log['uploads'] === [])
  <p class="sc-note mt-4">Nothing was uploaded in the last 24 hours.</p>
@else
  <div class="mt-4 overflow-x-auto">
    <table class="w-full text-left text-[14px]" id="recent-uploads">
      <thead class="sc-label"><tr><th class="py-2 pr-4 font-normal">Uploaded</th><th class="py-2 pr-4 font-normal">Character</th><th class="py-2 pr-4 text-right font-normal">Parts</th><th class="py-2 pr-4 text-right font-normal">Sessions</th><th class="py-2 text-right font-normal">Size</th></tr></thead>
      <tbody>
@foreach ($log['uploads'] as $upload)
        <tr class="border-t border-line"><td class="py-2 pr-4">{{ \App\View\Dates::moment($upload['at']) }}</td><td class="py-2 pr-4">{{ \App\View\Format::characterName($upload['character']) }}</td><td class="py-2 pr-4 text-right">{{ \App\View\Format::number($upload['parts']) }}</td><td class="py-2 pr-4 text-right">{{ \App\View\Format::number($upload['sessions']) }}</td><td class="py-2 text-right">{{ \App\View\Format::bytes($upload['bytes']) }}</td></tr>
@endforeach
      </tbody>
    </table>
  </div>
@endif
  <p class="mt-4 text-[14px]"><a href="/account/storage/history">Full upload history</a></p>
@if ($log['days'] !== [])
  <h3 class="sc-h3 mt-8">Requests by day</h3>
  <div class="mt-2 overflow-x-auto">
    <table class="w-full text-left text-[14px]">
      <thead class="sc-label"><tr><th class="py-2 pr-4 font-normal">Day (UTC)</th><th class="py-2 pr-4 text-right font-normal">Requests</th><th class="py-2 text-right font-normal">Compressed size</th></tr></thead>
      <tbody>
@foreach ($log['days'] as $day)
        <tr class="border-t border-line"><td class="py-2 pr-4">{{ \App\View\Dates::day($day['day']->toDateString()) }}</td><td class="py-2 pr-4 text-right">{{ \App\View\Format::number($day['requests']) }}</td><td class="py-2 text-right">{{ \App\View\Format::bytes($day['bytes']) }}</td></tr>
@endforeach
      </tbody>
    </table>
  </div>
@endif
</div>
