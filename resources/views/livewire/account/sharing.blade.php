<div>
  <x-rows>
@foreach ($groups as $group)
    <x-row :title="$group['label']" :body="$group['body']" wire:key="g-{{ $group['key'] }}">
@if (! $group['on'])
      <x-btn kind="danger" wire:click="remove('{{ $group['key'] }}')" wire:confirm="Remove {{ $group['label'] }} from the server? Its parts and numbers are deleted for all your characters. This cannot be undone.">Remove from server</x-btn>
@endif
      <x-switch :label="'Share '.$group['label']" :checked="$group['on']" :disabled="$group['locked']" wire:change="setShared('{{ $group['key'] }}', $event.target.checked)" />
    </x-row>
@endforeach
  </x-rows>
@if ($notice)
  <x-note role="status">{{ $notice }}</x-note>
@endif
</div>
