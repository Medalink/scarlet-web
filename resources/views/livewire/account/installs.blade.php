<div>
@if ($installs === [])
  <x-note>No app is linked. Open Scarlet on your PC and choose Link to my account.</x-note>
@else
  <x-rows>
@foreach ($installs as $install)
    <x-row :title="($install->name ?? 'Scarlet app').', linked '.($install->linked_at ?? $install->created_at)->format('j M Y')" :body="($install->last_seen_at ? 'Last upload '.$install->last_seen_at->diffForHumans() : 'Nothing uploaded yet').' · '.$install->characters_count.' '.\Illuminate\Support\Str::plural('character', $install->characters_count)" wire:key="i-{{ $install->id }}">
@if ($install->isRevoked())
      <x-state>Revoked</x-state>
@else
      <x-btn kind="danger" wire:click="revoke('{{ $install->id }}')" wire:confirm="Revoke this install? It can no longer upload. What it uploaded stays.">Revoke</x-btn>
@endif
    </x-row>
@endforeach
  </x-rows>
  <x-note>A revoked install can no longer upload. What it uploaded stays until you remove it.</x-note>
@endif
</div>
