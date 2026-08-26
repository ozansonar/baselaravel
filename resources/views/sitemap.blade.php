{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
@foreach ($urls as $url)
    <url>
        <loc>{{ $url['loc'] }}</loc>
        <lastmod>{{ $url['lastmod'] }}</lastmod>
        <changefreq>{{ $url['changefreq'] }}</changefreq>
        <priority>{{ $url['priority'] }}</priority>
@foreach ($url['alternates'] as $alternateLocale => $alternateUrl)
        <xhtml:link rel="alternate" hreflang="{{ $alternateLocale }}" href="{{ $alternateUrl }}"/>
@endforeach
@if (! empty($url['x_default']))
        <xhtml:link rel="alternate" hreflang="x-default" href="{{ $url['x_default'] }}"/>
@endif
@foreach ($url['images'] as $image)
        <image:image>
            <image:loc>{{ $image['loc'] }}</image:loc>
            <image:title>{{ $image['title'] }}</image:title>
        </image:image>
@endforeach
    </url>
@endforeach
</urlset>
