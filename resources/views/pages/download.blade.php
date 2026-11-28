@php
$website = ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => 'Scarlet', 'url' => rtrim((string) config('app.url'), '/').'/'];
@endphp
<x-layouts.sc :title="$app['installer'] ? 'Scarlet for Windows, version '.$app['version'] : 'Scarlet for Windows'" description="Scarlet is an addon and desktop app for WoW Forever. The addon writes a timestamped line for each thing you do and keeps it on your PC." current="download" :brand="false">
<script type="application/ld+json">@json($website)</script>
<div class="wrap pb-20">
  <section class="grid items-end gap-x-12 gap-y-6 border-b border-line pb-8 pt-10 md:grid-cols-[minmax(0,1fr)_auto]" aria-labelledby="h1">
    <div class="min-w-0">
      <h1 id="h1" class="sc-title" style="font-size:clamp(44px,6vw,60px)">Scarlet for Windows{{ $app['installer'] ? ', version '.$app['version'] : '' }}</h1>
      <p class="mt-3 max-w-[62ch] text-[15.5px]">Scarlet records what you do in WoW Forever and keeps it on your PC. Uploading to the website is optional and off until you turn it on.</p>
    </div>
    <div class="flex flex-wrap items-center gap-3"><x-get-app :version="false" /></div>
  </section>

  <section class="mt-8 grid gap-2 text-[15px]" aria-labelledby="h-downloads">
    <h2 id="h-downloads" class="vh">Download</h2>
    @if ($app['installer'])
      <p class="max-w-[72ch]">{{ $app['prerelease'] ? 'Beta version' : 'Version' }} <b class="text-fg">{{ $app['version'] }}</b>@if ($app['date']), published {{ \App\View\Dates::day($app['date']) }}@endif. Windows only. The installer sets up the app for your user and needs no administrator rights. It installs the addon for WoW Forever and keeps it current.</p>
      <p class="text-muted">Scarlet_{{ $app['version'] }}_x64-setup.exe{{ $app['installer_size'] ? ', '.number_format($app['installer_size'] / 1048576, 1).' MB' : '' }}, signed with Authenticode.@if ($app['sums']) <a href="{{ $app['sums'] }}">SHA256SUMS.txt</a> lists its checksum.@endif</p>
    @else
      <p>The newest version could not be looked up just now. Every version is on the releases page.</p>
    @endif
    <p>@if ($app['zip'])<a href="{{ $app['zip'] }}">Scarlet-{{ $app['version'] }}.zip</a>, the addon alone: unzip the <code>Scarlet</code> folder into <code>World of Warcraft\_forever_\Interface\AddOns</code>. @endif<a href="{{ $app['releases'] }}">All releases on GitHub</a></p>
  </section>

  <section class="mt-14" aria-labelledby="h-records">
    <h2 id="h-records" class="sc-h" style="font-size:32px">What Scarlet records</h2>
    <div class="mt-4">
      @foreach ([['The addon', 'Writes a timestamped line for each thing that happens in game: a step, a cast, a kill, a death, a coin looted. The lines stay in the game folder.', '/leaderboards'], ['The app', 'Reads those lines and draws your characters, sessions, feats, maps, charts and loot tables. It runs on your PC.', '/stats'], ['The website', 'Shows what players chose to upload: levelling, deaths, feats, loot and maps for everyone, and your own characters once you link the app.', '/']] as [$name, $text, $href])
        <div class="sc-dir"><span class="nm">{{ $name }}</span><span class="fact">{{ $text }}</span><span></span></div>
      @endforeach
    </div>
  </section>

  <section class="mt-14" id="never" aria-labelledby="h-never">
    <h2 id="h-never" class="sc-h" style="font-size:32px">What Scarlet never records</h2>
    <p class="mt-2 max-w-[70ch]">Scarlet does not store free text. Where a window holds someone's words, it keeps a count, a name or a price instead.</p>
    <div class="scroll-x mt-4"><table class="data">
      <thead><tr><th scope="col">Not recorded</th><th scope="col">Kept instead</th></tr></thead>
      <tbody>
        <tr><td>Chat text and whispers</td><td>Message and word counts per channel.</td></tr>
        <tr><td>Mail subjects and bodies</td><td>Who sent or received it, the money and the items.</td></tr>
        <tr><td>Auction notification text</td><td>The item, the count and the price.</td></tr>
        <tr><td>The text of UI errors</td><td>The error's name and how often it appeared.</td></tr>
        <tr><td>Support ticket and report text, and who a report was about</td><td>That a report was sent, and its type.</td></tr>
        <tr><td>Guild notes, the message of the day and rank names</td><td>Your rank number, and whether you are an officer or the leader.</td></tr>
        <tr><td>Calendar titles, friend notes and group finder comments</td><td>Counts: invites waiting, list sizes, listings found.</td></tr>
        <tr><td>Other players' GUIDs, account names and account friend tags</td><td class="wrap-cell">Character names only: players you group, trade, mail or roll against, players you inspect and the gear they wear, players a /who search lists, other rows of a battleground scoreboard, and the character a hardcore death notice names.</td></tr>
        <tr><td>Screenshots</td><td class="wrap-cell">The file name and the time. Scarlet never reads a screenshot, and takes them only if you turn on "Take screenshots at key moments" in Settings.</td></tr>
      </tbody>
    </table></div>
    <p class="mt-4 max-w-[70ch] text-fg">Your own chat is kept as counts too. Scarlet counts each <code>lol</code> and <code>rip</code> you type and stores the number, not the sentence.</p>
  </section>

  <section class="mt-14" id="release-notes" aria-labelledby="h-release-notes">
    <h2 id="h-release-notes" class="sc-h" style="font-size:32px">Release notes</h2>
    <p class="mt-2">Running v{{ $notes['build']['full'] }}. {{ \App\View\Format::count($notes['count'], 'release') }} published.</p>
    <x-release-notes :d="$notes" />
  </section>
</div>
</x-layouts.sc>
