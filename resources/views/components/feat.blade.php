{{-- One feat card of the character page. feat: a card of App\View\Pages\CharacterFeatWall (tier, icon, name,
     tier_name, detail and track, the share of each of the five tiers in percent). The track is hidden from assistive
     technology: the detail says the same in words. --}}
@props(['feat'])
<li class="feat t{{ $feat['tier'] }}"><span class="seal"><x-icon :name="$feat['icon']" /></span><div><p class="feat-n">{{ $feat['name'] }}@if ($feat['tier_name'])<small>{{ $feat['tier_name'] }}</small>@endif</p><p class="feat-d">{{ $feat['detail'] }}</p><span class="feat-to" aria-hidden="true">@foreach ($feat['track'] as $share)<i style="--at:{{ $share }}%"></i>@endforeach</span></div></li>
