<div>
@if (session('new-api-key'))
  <div class="note" role="status" style="color:var(--fg)">
    <p>Copy this key now. It is not shown again.</p>
    <p class="code" style="font-size:1rem;letter-spacing:0;word-break:break-all;max-width:100%">{{ session('new-api-key') }}</p>
  </div>
@endif
@if ($keys === [])
  <x-note>No keys yet.</x-note>
@else
  <x-rows>
@foreach ($keys as $key)
    <x-row :title="$key->name" :body="'Made '.$key->created_at->format('j M Y').' · '.($key->last_used_at ? 'last used '.$key->last_used_at->diffForHumans() : 'never used').' · 600 requests a minute'" wire:key="k-{{ $key->id }}">
      <x-btn kind="danger" wire:click="delete({{ $key->id }})" wire:confirm="Delete the key {{ $key->name }}? Anything using it stops working.">Delete</x-btn>
    </x-row>
@endforeach
  </x-rows>
@endif
  <form wire:submit="make" style="margin-top:1rem;display:flex;gap:.8rem;flex-wrap:wrap;align-items:center">
    <label class="vh" for="key-name">Name of the key</label>
    <input id="key-name" class="field" type="text" wire:model="name" maxlength="60" placeholder="Name of the key" autocomplete="off" style="flex:1 1 12rem;min-width:0">
    <x-btn kind="quiet" type="submit">Make a key</x-btn>
  </form>
@error('name')
  <x-note role="alert">Give the key a name.</x-note>
@enderror
@if ($notice)
  <x-note role="alert">{{ $notice }}</x-note>
@endif
  <x-note>The <a href="/developers">public API</a> needs no key at 60 requests a minute.</x-note>
</div>
