<div wire:poll.30s>
@if ($log['uploads'] === [])
  <p class="sc-note mt-4">Nothing has been uploaded yet.</p>
@else
  <div class="mt-4 overflow-x-auto">
    <table class="w-full text-left text-[14px]">
      <thead class="sc-label"><tr><th class="py-2 pr-4 font-normal" data-local-label="Time">Time (UTC)</th><th class="py-2 pr-4 font-normal">Character</th><th class="py-2 pr-4 text-right font-normal">Parts</th><th class="py-2 pr-4 text-right font-normal">Sessions</th><th class="py-2 text-right font-normal">Size</th></tr></thead>
      <tbody>
@foreach ($log['uploads'] as $upload)
        <tr class="border-t border-line"><td class="py-2 pr-4"><time datetime="{{ $upload['at']->toIso8601ZuluString() }}" data-local="j M Y H:i">{{ $upload['at']->format('j M Y H:i') }}</time></td><td class="py-2 pr-4">{{ \App\View\Format::characterName($upload['character']) }}</td><td class="py-2 pr-4 text-right">{{ \App\View\Format::number($upload['parts']) }}</td><td class="py-2 pr-4 text-right">{{ \App\View\Format::number($upload['sessions']) }}</td><td class="py-2 text-right">{{ \App\View\Format::bytes($upload['bytes']) }}</td></tr>
@endforeach
      </tbody>
    </table>
  </div>
@endif
@if ($log['installs'] !== [])
  <div class="sc-set">
@foreach ($log['installs'] as $install)
    <x-sc.set-row :title="$install['name']" :body="$install['last_seen_at'] ? 'Last contact '.$install['last_seen_at']->diffForHumans().', ' : 'No contact yet'" :time="$install['last_seen_at'] ? ['instant' => $install['last_seen_at']->utc()->toIso8601ZuluString(), 'format' => 'j M Y H:i', 'text' => $install['last_seen_at']->utc()->format('j M Y H:i').' UTC'] : null" wire:key="u-{{ $loop->index }}">
@if ($install['revoked'])
      <span class="sc-state">Revoked</span>
@endif
    </x-sc.set-row>
@endforeach
  </div>
@endif
@if ($log['days'] !== [])
  <div class="mt-4 overflow-x-auto">
    <table class="w-full text-left text-[14px]">
      <thead class="sc-label"><tr><th class="py-2 pr-4 font-normal">Day (UTC)</th><th class="py-2 pr-4 text-right font-normal">Requests</th><th class="py-2 text-right font-normal">Compressed size</th></tr></thead>
      <tbody>
@foreach ($log['days'] as $day)
        <tr class="border-t border-line"><td class="py-2 pr-4">{{ $day['day']->format('j M Y') }}</td><td class="py-2 pr-4 text-right">{{ \App\View\Format::number($day['requests']) }}</td><td class="py-2 text-right">{{ \App\View\Format::bytes($day['bytes']) }}</td></tr>
@endforeach
      </tbody>
    </table>
  </div>
@endif
</div>
