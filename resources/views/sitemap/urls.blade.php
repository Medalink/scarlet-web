{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($rows as $row)
<url><loc>{{ $origin.$row['path'] }}</loc>@if ($row['modified'] !== null)
<lastmod>{{ $row['modified'] }}</lastmod>@endif
</url>
@endforeach
</urlset>
