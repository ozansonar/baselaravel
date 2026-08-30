{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>{{ __('site.blog.feed_title', ['site' => $siteName]) }}</title>
        <link>{{ localized_route('blog.index') }}</link>
        <description>{{ $siteDesc }}</description>
        <language>{{ app()->getLocale() }}</language>
        <lastBuildDate>{{ $posts->first()?->published_at?->toRssString() ?? now()->toRssString() }}</lastBuildDate>
        <atom:link href="{{ route('feed') }}" rel="self" type="application/rss+xml" />
        @foreach($posts as $post)
        <item>
            <title>{{ $post->title }}</title>
            <link>{{ route('blog.show', [$post->category->slug, $post->slug]) }}</link>
            <guid isPermaLink="true">{{ route('blog.show', [$post->category->slug, $post->slug]) }}</guid>
            <description><![CDATA[{{ $post->excerpt ?? Str::limit(strip_tags($post->body), 300) }}]]></description>
            <pubDate>{{ $post->published_at?->toRssString() }}</pubDate>
            <category>{{ $post->category->name }}</category>
        </item>
        @endforeach
    </channel>
</rss>
