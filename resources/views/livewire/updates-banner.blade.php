<div>
@if ($feed['showBanner'] && $feed['current'])
  <section class="upd" aria-label="{{ $feed['title'] }}">
    <div class="wrap">
      <details>
        <summary><b>{{ $feed['title'] }}</b><span>{{ $feed['current']['headline'] }}<small>v{{ $feed['current']['version'] }}</small></span><i>View updates</i></summary>
        <div class="upd-body">
@foreach ($feed['releases'] as $release)
          <section wire:key="u-{{ $release['version'] }}">
            <h2>{{ $release['headline'] }} <small>v{{ $release['version'] }}</small></h2>
            <p>{{ $release['summary'] }}</p>
@foreach (\App\View\Pages\ReleaseNotesPage::KINDS as $kind => $label)
@if (! empty($release['sections'][$kind]))
            <h3>{{ $label }}</h3>
            <ul>@foreach ($release['sections'][$kind] as $line)<li>{{ $line }}</li>@endforeach</ul>
@endif
@endforeach
          </section>
@endforeach
@if ($feed['hiddenCount'] > 0)
          <p class="upd-more">Plus {{ \App\View\Format::count($feed['hiddenCount'], 'earlier release') }} in the <a href="/release-notes">release notes</a>.</p>
@endif
        </div>
      </details>
      <div class="upd-act">
        <a href="/release-notes">All release notes</a>
        <x-btn kind="quiet" wire:click="dismiss">Dismiss</x-btn>
        <x-btn kind="quiet" wire:click="markRead">Mark as read</x-btn>
      </div>
    </div>
  </section>
@endif
</div>
