<x-layouts.app title="Download the Scarlet app" current="download" description="Download the Scarlet desktop app for Windows. It installs the addon for WoW Forever and keeps it current.">
<div class="wrap">
  <x-page-head title="Download the Scarlet app" :single="true">
    <x-slot:sub>Windows only. The installer sets up the app for your user and needs no administrator rights.</x-slot:sub>
  </x-page-head>
  <div class="dl">
    <section class="dl-main" aria-labelledby="h-get">
      <h2 id="h-get" class="vh">Installer</h2>
@if ($app['installer'])
      <p class="dl-ver">{{ $app['prerelease'] ? 'Beta version' : 'Version' }} <b>{{ $app['version'] }}</b>@if ($app['date']), published {{ \Illuminate\Support\Carbon::parse($app['date'])->format('j M Y') }}@endif</p>
      <p><x-get-app :version="false" /></p>
      <p class="dl-meta">Scarlet_{{ $app['version'] }}_x64-setup.exe{{ $app['installer_size'] ? ', '.number_format($app['installer_size'] / 1048576, 1).' MB' : '' }}, signed with Authenticode.
@if ($app['sums'])
        <a href="{{ $app['sums'] }}">SHA256SUMS.txt</a> lists its checksum.
@endif
      </p>
@if ($app['prerelease'])
      <x-note>Every version so far is a beta. The app checks for newer versions and offers them in Settings, and you can switch between Beta and Release there.</x-note>
@endif
@else
      <x-note>The newest version could not be looked up just now. Every version is on the releases page.</x-note>
      <p><x-get-app /></p>
@endif
    </section>
    <section class="dl-side" aria-labelledby="h-does">
      <h2 id="h-does">What the app does</h2>
      <ul>
        <li>Installs the Scarlet addon in <code>World of Warcraft\_forever_\Interface\AddOns</code> and keeps it current. A copy that CurseForge or Wago manages is left alone.</li>
        <li>Reads what the addon recorded and shows your overview, timeline, maps, charts and loot tables on your PC.</li>
        <li>Uploads to this website only after you turn uploading on in the app, and only the groups you leave on.</li>
      </ul>
    </section>
    <section class="dl-more" aria-labelledby="h-other">
      <h2 id="h-other">Other downloads</h2>
      <ul class="dl-list">
@if ($app['zip'])
        <li><a href="{{ $app['zip'] }}">Scarlet-{{ $app['version'] }}.zip</a><span>The addon alone. Unzip the <code>Scarlet</code> folder into <code>World of Warcraft\_forever_\Interface\AddOns</code>.</span></li>
@endif
        <li><a href="{{ $app['releases'] }}">All releases on GitHub</a><span>Every version, with its notes and files.</span></li>
      </ul>
    </section>
  </div>
</div>
</x-layouts.app>
