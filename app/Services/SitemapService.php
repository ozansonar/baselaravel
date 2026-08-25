<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

final class SitemapService
{
    /**
     * Generate sitemap XML content.
     *
     * @return array<int, array{loc: string, lastmod: string, changefreq: string, priority: string}>
     */
    public function generateUrls(): array
    {
        // Deliberately not scoped to a language: a sitemap should list every
        // language's URLs. Each translation is its own row with its own slug,
        // so every language version appears once and the output does not depend
        // on the visitor's locale.
        return Cache::remember('sitemap.urls', 3600, function (): array {
            $urls = [];

            // Home — lastmod = latest published content date.
            $latestBlogRaw = BlogPost::published()->max('updated_at');
            $latestUpdate = $latestBlogRaw ? Carbon::parse($latestBlogRaw) : now();

            $urls[] = [
                'loc' => url('/'),
                'lastmod' => $latestUpdate->toW3cString(),
                'changefreq' => 'daily',
                'priority' => '1.0',
            ];

            // Static pages
            $staticPages = [
                ['url' => route('blog.index'), 'priority' => '0.8', 'freq' => 'daily'],
                ['url' => route('gallery'), 'priority' => '0.6', 'freq' => 'monthly'],
                ['url' => route('contact'), 'priority' => '0.6', 'freq' => 'monthly'],
                ['url' => route('faq'), 'priority' => '0.5', 'freq' => 'monthly'],
            ];

            foreach ($staticPages as $page) {
                $urls[] = [
                    'loc' => $page['url'],
                    'lastmod' => $latestUpdate->toW3cString(),
                    'changefreq' => $page['freq'],
                    'priority' => $page['priority'],
                ];
            }

            // Blog categories
            $blogCategories = BlogCategory::active()->sorted()->get(['slug', 'updated_at']);
            foreach ($blogCategories as $blogCategory) {
                $urls[] = [
                    'loc' => route('blog.category', $blogCategory->slug),
                    'lastmod' => $blogCategory->updated_at->toW3cString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.7',
                ];
            }

            // Blog posts
            $blogPosts = BlogPost::published()->with('category')->get(['id', 'blog_category_id', 'slug', 'updated_at']);
            foreach ($blogPosts as $blogPost) {
                $urls[] = [
                    'loc' => route('blog.show', [$blogPost->category->slug, $blogPost->slug]),
                    'lastmod' => $blogPost->updated_at->toW3cString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.7',
                ];
            }

            // Dynamic pages
            $pages = Page::published()->get(['slug', 'updated_at']);
            foreach ($pages as $page) {
                $urls[] = [
                    'loc' => route('pages.show', $page->slug),
                    'lastmod' => $page->updated_at->toW3cString(),
                    'changefreq' => 'monthly',
                    'priority' => '0.5',
                ];
            }

            return $urls;
        });
    }

    public function clearCache(): void
    {
        Cache::forget('sitemap.urls');
    }
}
