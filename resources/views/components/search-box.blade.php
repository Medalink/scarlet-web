{{-- The search field of the search page. --}}
@props(['value' => '', 'action' => '/search'])
<form class="search-box" role="search" action="{{ $action }}"><label class="vh" for="sq">Search for</label><input id="sq" name="q" type="search" value="{{ $value }}"><button class="btn" type="submit">Search</button></form>
