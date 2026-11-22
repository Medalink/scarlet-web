{{-- The search field of the search page. Under the site root it is a combobox (resources/js/site/search.js). --}}
@props(['value' => '', 'action' => '/search'])
<form class="search-box" role="search" action="{{ $action }}"@if ($action === '/search') data-suggest="/search/suggest"@endif><label class="vh" for="sq">Search for</label><div class="find"><input id="sq" name="q" type="search" value="{{ $value }}"></div><button class="btn" type="submit">Search</button></form>
