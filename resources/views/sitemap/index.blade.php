{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($paths as $path)
<sitemap><loc>{{ $origin.$path }}</loc></sitemap>
@endforeach
</sitemapindex>
