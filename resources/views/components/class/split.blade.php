{{-- Shares in one bar with a key: block is App\Support\ClassPage\Split. --}}
@props(['block', 'icons'])
@php($hue = fn ($part) => $part->tone instanceof \App\Support\ClassPage\Hue)
<div class="split" aria-hidden="true">@foreach ($block->parts as $part)<i @if (! $hue($part)) class="{{ $part->tone->value }}"@endif style="flex:{{ $part->value }}@if ($hue($part));background:{{ $part->tone->css }}@endif"></i>@endforeach</div>
<p class="split-key">@foreach ($block->parts as $part)<span><i @if (! $hue($part)) class="{{ $part->tone->value }}"@else style="background:{{ $part->tone->css }}"@endif></i>{{ $part->label }}</span>@endforeach</p>
