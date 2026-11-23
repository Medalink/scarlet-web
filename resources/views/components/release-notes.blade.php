{{-- The release notes: the build that is running, the releases to pick from and one release with its changes. d: App\View\Pages\ReleaseNotesPage::data. --}}
@props(['d'])
  <section class="mod first last" aria-labelledby="h-build" style="padding-block:1.6rem 0">
    <h2 id="h-build" class="vh">Running now</h2>
    <x-figures :items="$d['build']['figures']" />
    <x-table :columns="[['label' => 'Range'], ['label' => 'Detail'], ['label' => 'Additions', 'numeric' => true], ['label' => 'Deletions', 'numeric' => true]]" :rows="$d['build']['rows']" style="margin-top:1.4rem" />
  </section>
@if ($d['release'] === null)
  <x-note>No release notes have been published yet.</x-note>
@else
@php($r = $d['release'])
  <div class="rel">
    <nav class="rel-nav" aria-label="Releases">
      <h2>Releases</h2>
@foreach ($d['rail'] as $entry)
      <a href="/download?v={{ urlencode($entry['version']) }}#release-notes"@if ($entry['current']) aria-current="true"@endif><b>v{{ $entry['version'] }}</b><span>{{ $entry['date'] }}</span><i>{{ \App\View\Format::count($entry['total'], 'change') }}</i></a>
@endforeach
    </nav>
    <article class="rel-body" aria-labelledby="h-rel">
      <header>
        <h2 id="h-rel">v{{ $r['version'] }}</h2>
        <p class="rel-meta">@if ($r['date']){{ $r['date'] }}@endif @if ($r['since'])<span>Since v{{ $r['since'] }}@if ($r['days']) · {{ \App\View\Format::count($r['days'], 'day') }}@endif</span>@endif</p>
        <p class="rel-title">{{ $r['title'] }}</p>
@if ($r['total'] > 0)
        <div class="mix" aria-hidden="true">@foreach ($r['counts'] as $kind => $count)<i class="{{ $kind }}" style="width:{{ round($count / $r['total'] * 100, 2) }}%"></i>@endforeach</div>
@endif
      </header>
      <x-seg label="Kind of change" :items="$r['filters']" />
@foreach ($r['areas'] as $area)
      <section class="rel-area">
        <div class="rel-area-head"><h3>{{ $area['title'] }}</h3>@if ($area['about'] !== '')<span>{{ $area['about'] }}</span>@endif</div>
        <ul>
@foreach ($area['rows'] as $row)
          <li><span class="rn-tag {{ $row['kind'] }}" title="{{ \App\View\Pages\ReleaseNotesPage::KINDS[$row['kind']] }}" aria-label="{{ \App\View\Pages\ReleaseNotesPage::KINDS[$row['kind']] }}">{{ strtoupper(substr(\App\View\Pages\ReleaseNotesPage::KINDS[$row['kind']], 0, 3)) }}</span><p>{{ $row['text'] }}</p></li>
@endforeach
        </ul>
      </section>
@endforeach
@if ($r['areas'] === [])
      <x-note>No changes of this kind in v{{ $r['version'] }}.</x-note>
@endif
    </article>
  </div>
@endif
