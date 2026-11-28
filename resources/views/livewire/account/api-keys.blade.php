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
@php($expired = $key->expires_at !== null && $key->expires_at->isPast())
    <x-sc.set-row :title="$key->name" :body="\App\View\Dates::join('Made ', \App\View\Dates::moment($key->created_at), ' · ', ...($key->last_used_at ? ['last used ', \App\View\Dates::moment($key->last_used_at)] : ['never used']), ...($key->expires_at ? [$expired ? ' · expired ' : ' · expires ', \App\View\Dates::day($key->expires_at)] : []))" wire:key="k-{{ $key->id }}">
@if ($expired)
      <span class="sc-state">Expired</span>
@endif
      <button type="button" class="sc-btn danger small" wire:click="delete({{ $key->id }})" wire:confirm="Delete the key {{ $key->name }}? Anything using it stops working.">Delete</button>
    </x-sc.set-row>
@endforeach
  </div>
@endif
@if ($battlenet)
  <form wire:submit="make" class="mt-4 flex flex-wrap items-center gap-3">
    <label class="sr-only" for="key-name">Name of the key</label>
    <input id="key-name" class="sc-field min-w-0 flex-1 basis-48" type="text" wire:model="name" maxlength="60" placeholder="Name of the key" autocomplete="off">
    <label class="sr-only" for="key-days">Days until the key expires</label>
    <select id="key-days" class="sc-field" wire:model="days">
@foreach ($choices as $choice)
      <option value="{{ $choice }}">Expires in {{ $choice }} days</option>
@endforeach
    </select>
    <button type="submit" class="sc-btn quiet">Make a key</button>
  </form>
@error('name')
  <p class="sc-note mt-3" role="alert">Give the key a name.</p>
@enderror
@error('days')
  <p class="sc-note mt-3" role="alert">A key lasts 1 to {{ \App\Models\Account::API_KEY_MAX_DAYS }} days.</p>
@enderror
@elseif ($provider->isConfigured() && $provider->isOAuth())
  <p class="sc-note mt-4" id="key-battlenet">API keys need a Battle.net sign-in. <a href="/auth/{{ $provider->value }}/redirect">Sign in with Battle.net</a>.</p>
@elseif ($development)
  <form method="post" action="/auth/development" class="sc-note mt-4" id="key-battlenet">
    @csrf
    <input type="hidden" name="as" value="battlenet">
    API keys need a Battle.net sign-in. <button type="submit" class="sc-btn quiet small">Sign in with development Battle.net</button>
  </form>
@else
  <p class="sc-note mt-4" id="key-battlenet">API keys need a Battle.net sign-in, which this server does not offer.</p>
@endif
@if ($notice)
  <p class="sc-note mt-3" role="alert">{{ $notice }}</p>
@endif
</div>
