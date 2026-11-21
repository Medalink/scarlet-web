<div>
  <h2 style="margin-top:2rem;font-size:1.9rem">Sign-in methods</h2>
  <x-rows>
@foreach ($providers as $provider)
@if (isset($linked[$provider->value]))
    <x-row :title="$provider->label()" :body="'Linked '.$linked[$provider->value]->created_at->format('j M Y')" wire:key="m-{{ $provider->value }}">
      <x-state :on="true">Linked</x-state>
@if (count($linked) > 1)
      <x-btn kind="quiet" wire:click="unlink('{{ $provider->value }}')" wire:confirm="Unlink {{ $provider->label() }}? You can still sign in with the other method.">Unlink</x-btn>
@endif
    </x-row>
@else
    <x-row :title="$provider->label()" :body="'Not linked.'.($provider === \App\Models\IdentityProvider::Battlenet ? ' Your BattleTag is never stored.' : '')" wire:key="m-{{ $provider->value }}">
@if ($provider->isConfigured())
      <x-btn kind="quiet" :href="'/auth/'.$provider->value.'/redirect'">Link</x-btn>
@else
      <x-state>Not set up on this server</x-state>
@endif
    </x-row>
@endif
@endforeach
  </x-rows>
@if ($notice)
  <x-note role="alert">{{ $notice }}</x-note>
@endif
</div>
