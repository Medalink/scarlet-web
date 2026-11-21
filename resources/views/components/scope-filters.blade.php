{{-- The ruleset and server filters of a dashboard page. filters: the answer of App\View\Pages\Filters::for(). --}}
@props(['filters'])
@if ($filters['rulesetsPick'])<x-pick label="Ruleset" :options="$filters['rulesetsPick']" :selected="$filters['rulesetSelected']" />@else<x-seg label="Ruleset" :items="$filters['rulesets']" />@endif
@if ($filters['servers'])<x-seg label="Server" :items="$filters['servers']" />@endif
