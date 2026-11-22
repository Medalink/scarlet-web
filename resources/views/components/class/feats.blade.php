{{-- The class's feats: feats is a list of App\Support\ClassPage\FeatCard, in the order the page shows them. --}}
@props(['feats', 'name', 'icons'])
@php
    $tiers = \App\Support\Feat::TIERS;
    $count = fn (int $tier) => count(array_filter($feats, fn ($feat) => $feat->tier === $tier));
@endphp
<section class="recs" aria-labelledby="h-feats">
<h2 id="h-feats">{{ $name }} feats</h2>
<p class="feats-sum"><span><b>{{ count(array_filter($feats, fn ($feat) => $feat->tier >= 1)) }}</b>of {{ count($feats) }} feats</span>@foreach ([5, 4, 3, 2, 1] as $tier)@if ($tier <= 3 || $count($tier) > 0)<span><b>{{ $count($tier) }}</b>{{ $tiers[$tier - 1] }}</span>@endif @endforeach</p>
<ul class="feats feats-top">@foreach ($feats as $feat)<li class="feat t{{ $feat->tier }}"><span class="seal">@if ($feat->icon)<x-class.icon :icon="$feat->icon" :icons="$icons" />@else<x-icon :name="$feat->glyph" />@endif</span><div><p class="feat-n">{{ $feat->name }}@if ($feat->tier >= 1)<small>{{ $tiers[$feat->tier - 1] }}</small>@endif</p><p class="feat-d">{{ $feat->detail }}</p><span class="feat-to" aria-hidden="true">@foreach ($feat->track as $share)<i style="--at:{{ max(0, min(100, $share)) }}%"></i>@endforeach</span></div></li>@endforeach</ul>
</section>
