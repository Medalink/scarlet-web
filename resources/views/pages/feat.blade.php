@php($family = $d['family'])
<x-layouts.sc :title="$d['name'].' feat'" :description="$d['name'].': '.$d['line']" current="feats" :canonical="'/feats/'.$d['key']">
<div class="wrap pb-20 pt-8">
  <p class="text-[14.5px]"><a href="/feats">Feats</a> <span class="text-muted">· {{ $d['collection'] }}</span></p>
  <div class="mt-4 flex items-center gap-6">
    <x-crest :glyph="$d['glyph']" :feat-key="$d['key']" :tier="$d['tier']" :size="104" :label="$d['name']" />
    @if ($d['account'])<x-crest :glyph="$d['glyph']" scope="account" :tier="$d['account']['tier']" :size="104" :label="$d['name'].' on the account'" />@endif
    <div class="min-w-0">
      <h1 class="sc-title text-[40px] md:text-[60px]">{{ $d['name'] }}</h1>
      <p class="mt-3 text-[15px]">{{ $d['line'] }}@if ($d['mine']) {{ $d['mine']['name'] }} has {{ $d['mine']['count'] }}.@endif</p>
    </div>
  </div>

  <section class="mt-10 grid gap-10 lg:grid-cols-[400px_minmax(0,1fr)]">
    <div class="sc-panel self-start px-5 py-5">
      <h2 class="sc-h text-[24px]">Tiers</h2>
      <x-sc.ladder class="mt-5" :rows="$d['ladder']" :feat="['glyph' => $d['glyph'], 'key' => $d['key'], 'family' => $family]" />
      <p class="sc-label mt-5">{{ $d['shares'] ? 'Share of characters holding each tier or higher' : 'Shares appear once 20 characters are counted' }}</p>
      @if ($d['account'])
        <h2 class="sc-h mt-8 text-[24px]">Account tiers</h2>
        @if ($d['account']['card'])<x-sc.feat class="mt-5" :feat="$d['account']['card']" />@endif
        <x-sc.ladder class="mt-5" :rows="$d['account']['ladder']" :feat="['glyph' => $d['glyph'], 'key' => $d['key']]" scope="account" />
      @endif
    </div>
    <div class="grid gap-10">
      <div>
        <h2 class="sc-h text-[30px]">Highest counts</h2>
        @forelse ($d['top'] as $row)
          @if ($loop->first)<ol class="mt-4 grid gap-px border-t border-line">@endif
          <li class="flex items-baseline gap-3 border-b border-line py-2.5 text-[14.5px]">
            <span class="w-6 text-muted">{{ $loop->iteration }}</span>
            <a href="{{ $row['href'] }}" class="font-semibold">{{ $row['name'] }}</a>
            @if ($row['tierName'])<span class="sc-chip t{{ $row['tier'] }}">{{ $row['tierName'] }}</span>@endif
            <span class="ml-auto text-muted">{{ $row['count'] }}</span>
          </li>
          @if ($loop->last)</ol>@endif
        @empty
          <p class="sc-note mt-3">No public character has counted any yet.</p>
        @endforelse
      </div>
      <div>
        <h2 class="sc-h text-[30px]">Reached a tier last</h2>
        @forelse ($d['recent'] as $row)
          @if ($loop->first)<ul class="mt-4 grid gap-px border-t border-line">@endif
          <li class="flex items-baseline gap-3 border-b border-line py-2.5 text-[14.5px]">
            <a href="{{ $row['href'] }}" class="font-semibold">{{ $row['name'] }}</a>
            <span class="sc-chip t{{ $row['tier'] }}">{{ $row['tierName'] }}</span>
            <time class="ml-auto text-muted" datetime="{{ $row['at'] }}" data-local="D j M Y">{{ $row['day'] }}</time>
          </li>
          @if ($loop->last)</ul>@endif
        @empty
          <p class="sc-note mt-3">No public character has reached a tier since the site began recording them.</p>
        @endforelse
      </div>
    </div>
  </section>

  @if ($d['others'] !== [])
    <section class="mt-12">
      <x-sc.collection :name="$d['collection']" :family="$family" :feats="$d['others']" :counts="false" class="!border-t-0" />
    </section>
  @endif
</div>
</x-layouts.sc>
