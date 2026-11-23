<div>
@if (session('new-api-key'))
  <div class="sc-secret" role="status">
    <p class="sc-label">Copy this key now. It is not shown again.</p>
    <code>{{ session('new-api-key') }}</code>
  </div>
@endif
@if ($keys === [])
  <p class="sc-note mt-4">No keys yet.</p>
@else
  <div class="sc-set">
@foreach ($keys as $key)
    <x-sc.set-row :title="$key->name" :body="'Made '.$key->created_at->format('j M Y').' · '.($key->last_used_at ? 'last used '.$key->last_used_at->diffForHumans() : 'never used')" wire:key="k-{{ $key->id }}">
      <button type="button" class="sc-btn danger small" wire:click="delete({{ $key->id }})" wire:confirm="Delete the key {{ $key->name }}? Anything using it stops working.">Delete</button>
    </x-sc.set-row>
@endforeach
  </div>
@endif
  <form wire:submit="make" class="mt-4 flex flex-wrap items-center gap-3">
    <label class="sr-only" for="key-name">Name of the key</label>
    <input id="key-name" class="sc-field min-w-0 flex-1 basis-48" type="text" wire:model="name" maxlength="60" placeholder="Name of the key" autocomplete="off">
    <button type="submit" class="sc-btn quiet">Make a key</button>
  </form>
@error('name')
  <p class="sc-note mt-3" role="alert">Give the key a name.</p>
@enderror
@if ($notice)
  <p class="sc-note mt-3" role="alert">{{ $notice }}</p>
@endif
  <p class="sc-note mt-3">The <a href="/developers">public API</a> needs no key at 60 requests a minute.</p>
</div>
