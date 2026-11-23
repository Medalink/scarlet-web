{{-- The running version in the footer, linked to the release notes. The title carries the build and the commit. --}}
@props(['base' => ''])
@php($stamp = \App\View\Pages\ReleaseNotesPage::stamp())
<a {{ $attributes->class(['ver']) }} href="{{ $base }}/download#release-notes" title="Build {{ $stamp['full'] }}">{{ $stamp['text'] }}</a>
