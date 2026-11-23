<div>
  <div class="sc-set">
@foreach ($providers as $provider)
@if (isset($linked[$provider->value]))
    <x-sc.set-row :title="$provider->label()" :body="'Linked '.$linked[$provider->value]->created_at->format('j M Y').' · '.$linked[$provider->value]->signInKey()" wire:key="m-{{ $provider->value }}">
      <span class="sc-state on">Linked</span>
@if (count($linked) > 1)
      <button type="button" class="sc-btn quiet small" wire:click="unlink('{{ $provider->value }}')" wire:confirm="Unlink {{ $provider->label() }}? You can still sign in with the other method.">Unlink</button>
@endif
    </x-sc.set-row>
@else
    <x-sc.set-row :title="$provider->label()" :body="'Not linked.'.($provider === \App\Models\IdentityProvider::Battlenet ? ' Your BattleTag is never stored.' : '')" wire:key="m-{{ $provider->value }}">
@if ($provider->isConfigured())
      <a class="sc-btn quiet small" href="/auth/{{ $provider->value }}/redirect">Link</a>
@else
      <span class="sc-state">Not set up on this server</span>
@endif
    </x-sc.set-row>
@endif
@endforeach
  </div>
@if ($notice)
  <p class="sc-note mt-3" role="alert">{{ $notice }}</p>
@endif
</div>
