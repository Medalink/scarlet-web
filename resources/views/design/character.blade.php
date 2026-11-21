@php($base = App\View\Design\Gallery::BASE)
<x-layouts.app title="Maren-Ashlow: character" current="search" :signed-in="true" :base="$base">
<x-map-head name="Maren-Ashlow" :dot="true" :facts="$d['facts']" :progress="$d['progress']" :map="$d['map']" map-label="Every path the character walked in Saltmarsh Reach, with deaths as crosses"
  :legend="[['mark' => 'path', 'label' => 'every path walked in Saltmarsh Reach'], ['mark' => 'death', 'label' => 'death']]" />
<div class="wrap">
  <x-owner-bar state="Public page" :on="true" :facts="$d['facts_owner']" :link="['href' => $base.'/account', 'label' => 'Sharing and privacy']" switch-label="Public page" :public="true" />
  <x-week title="This week" range="Mon 16 to Sun 22 Nov" :items="$d['week']" />
  <x-sheet>
    <x-module span="w8" :first="true" id="h-pace" heading="Levelling against other Enhanced, PvE characters">
      <x-plot id="pace" label="This character's played hours to each level against the median and middle half of its ruleset" :spec="$d['pace']" />
      <x-key :items="[['label' => 'Maren-Ashlow', 'class' => 'k-line', 'style' => 'background:var(--fg)'], ['label' => 'Median', 'class' => 'k-line', 'style' => 'background:var(--accent)'], ['label' => 'Middle half', 'style' => 'background:rgba(255,45,85,.3)']]" />
      <x-readout id="pace-out" :line="$d['paceLine']" />
    </x-module>
    <x-module :last="true" id="h-gold">
      <x-slot:heading>Gold <span class="priv">Only you</span></x-slot:heading>
      <p class="big"><x-money :copper="641200" /></p><p class="sub">{{ App\View\Format::moneyText(95200, true) }} over the last 7 sessions</p>
      <x-plot id="gold" label="Balance at the end of each of the last 30 sessions" :spec="$d['balance']" />
    </x-module>
    <x-module span="w12" :first="true" :last="true">
      <x-note style="margin:0">Below this the page is the character overview as it is today: experience by level, dungeons and raids, played with, deaths, play time by day, distance, play history. "Played with" shows names to the owner only when Social is shared; visitors see a name only when that player's own character is public.</x-note>
    </x-module>
  </x-sheet>
</div>
</x-layouts.app>
