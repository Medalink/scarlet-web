<div wire:poll.30s>
@if ($log['uploads'] === [])
  <x-note>Nothing has been uploaded yet.</x-note>
@else
  <x-table :columns="[['label' => 'Time (UTC)', 'local' => 'Time'], ['label' => 'Character'], ['label' => 'Parts', 'numeric' => true], ['label' => 'Sessions', 'numeric' => true], ['label' => 'Size', 'numeric' => true]]" :rows="array_map(fn (array $upload): array => ['cells' => [['instant' => $upload['at']->toIso8601ZuluString(), 'format' => 'j M Y H:i', 'text' => $upload['at']->format('j M Y H:i')], \App\View\Format::characterName($upload['character']), \App\View\Format::number($upload['parts']), \App\View\Format::number($upload['sessions']), \App\View\Format::bytes($upload['bytes'])]], $log['uploads'])" />
@endif
@if ($log['installs'] !== [])
  <x-rows>
@foreach ($log['installs'] as $install)
    <x-row :title="$install['name']" :body="$install['last_seen_at'] ? 'Last contact '.$install['last_seen_at']->diffForHumans().', ' : 'No contact yet'" :time="$install['last_seen_at'] ? ['instant' => $install['last_seen_at']->utc()->toIso8601ZuluString(), 'format' => 'j M Y H:i', 'text' => $install['last_seen_at']->utc()->format('j M Y H:i').' UTC'] : null" wire:key="u-{{ $loop->index }}">
@if ($install['revoked'])
      <x-state>Revoked</x-state>
@endif
    </x-row>
@endforeach
  </x-rows>
@endif
@if ($log['days'] !== [])
  <x-table style="margin-top:1.1rem" :columns="[['label' => 'Day (UTC)'], ['label' => 'Requests', 'numeric' => true], ['label' => 'Compressed size', 'numeric' => true]]" :rows="array_map(fn (array $day): array => ['cells' => [$day['day']->format('j M Y'), \App\View\Format::number($day['requests']), \App\View\Format::bytes($day['bytes'])]], $log['days'])" />
@endif
</div>
