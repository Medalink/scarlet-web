<div>
@if ($installs === [])
  <p class="sc-note mt-4">No app is linked. Open Scarlet on your PC and choose Link to my account. The app is on the <a href="/download">download page</a>.</p>
@else
  <div class="sc-set">
@foreach ($installs as $install)
    <x-sc.set-row :title="\App\View\Dates::join(($install->name ?? 'Scarlet app').', linked ', \App\View\Dates::moment($install->linked_at ?? $install->created_at))" :body="\App\View\Dates::implode(' · ', [$install->last_seen_at ? \App\View\Dates::join('Last upload ', \App\View\Dates::moment($install->last_seen_at)) : 'Nothing uploaded yet', \App\View\Format::count($install->characters_count, 'character')])" wire:key="i-{{ $install->id }}">
@if ($install->isRevoked())
      <span class="sc-state">Revoked</span>
@else
      <button type="button" class="sc-btn danger small" wire:click="revoke('{{ $install->id }}')" wire:confirm="Revoke this install? It can no longer upload. What it uploaded stays.">Revoke</button>
@endif
    </x-sc.set-row>
@endforeach
  </div>
  <p class="sc-note mt-3">A revoked install can no longer upload. What it uploaded stays until you remove it.</p>
@endif
</div>
