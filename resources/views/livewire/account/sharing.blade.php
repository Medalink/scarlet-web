<div>
  <div class="sc-set">
@foreach ($groups as $group)
    <x-sc.set-row :title="$group['label']" :body="$group['body']" wire:key="g-{{ $group['key'] }}">
@if (! $group['on'])
      <button type="button" class="sc-btn danger small" wire:click="remove('{{ $group['key'] }}')" wire:confirm="Remove {{ $group['label'] }} from the server? Its parts and numbers are deleted for all your characters. This cannot be undone.">Remove from server</button>
@endif
      <x-sc.toggle :label="'Share '.$group['label']" :checked="$group['on']" :disabled="$group['locked']" wire:change="setShared('{{ $group['key'] }}', $event.target.checked)" />
    </x-sc.set-row>
@endforeach
  </div>
@if ($notice)
  <p class="sc-note mt-3" role="status">{{ $notice }}</p>
@endif
</div>
