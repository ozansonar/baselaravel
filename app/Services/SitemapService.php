<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\CityLandingPage;
use App\Models\Page;
use App\Models\Product;
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
        return Cache::remember('sitemap.urls', 3600, function (): array {
            $urls = [];

            // Home — lastmod = en son eklenen ürün/blog tarihi.
            // Eloquent::max() aggregate fonksiyonu cast'leri yok sayar ve
            // string döner; her değeri Carbon::parse ile sarmalayıp sonra
            // PHP max() ile en günceli al → toW3cString güvenle çağrılır.
            $latestUpdate = collect([
                Product::active()->max('updated_at'),
                BlogPost::published()->max('updated_at'),
            ])
                ->filter()
                ->map(fn ($value) => Carbon::parse($value))
                ->max() ?? now();
            $urls[] = [
                'loc' => url('/'),
                'lastmod' => $latestUpdate->toW3cString(),
                'changefreq' => 'daily',
                'priority' => '1.0',
            ];

            // Static pages — sabit tarih yerine son ürün/içerik güncelleme
            $staticPages = [
                ['url' => route('products.all'), 'priority' => '0.9', 'freq' => 'weekly'],
                ['url' => route('gallery'), 'priority' => '0.6', 'freq' => 'monthly'],
                ['url' => route('contact'), 'priority' => '0.6', 'freq' => 'monthly'],
                ['url' => route('faq'), 'priority' => '0.5', 'freq' => 'monthly'],
                ['url' => route('sitemap-page'), 'priority' => '0.4', 'freq' => 'weekly'],
            ];

            foreach ($staticPages as $page) {
                $urls[] = [
                    'loc' => $page['url'],
                    'lastmod' => $latestUpdate->toW3cString(),
                    'changefreq' => $page['freq'],
                    'priority' => $page['priority'],
                ];
            }

            // Categories
            $categories = Category::active()->get(['slug', 'updated_at']);
            foreach ($categories as $category) {
                $urls[] = [
                    'loc' => route('products.index', $category->slug),
                    'lastmod' => $category->updated_at->toW3cString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.8',
                ];
            }

            // Products
            $products = Product::active()->get(['slug', 'updated_at']);
            foreach ($products as $product) {
                $urls[] = [
                    'loc' => route('products.show', $product->slug),
                    'lastmod' => $product->updated_at->toW3cString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.7',
                ];
            }

            // Blog index — son yayınlanan yazının tarihi.
            // max() aggregate string döner → Carbon::parse ile sarmal.
            $latestBlogRaw = BlogPost::published()->max('updated_at');
            $latestBlog = $latestBlogRaw ? Carbon::parse($latestBlogRaw) : now();
            $urls[] = [
                'loc' => route('blog.index'),
                'lastmod' => $latestBlog->toW3cString(),
                'changefreq' => 'daily',
                'priority' => '0.8',
            ];

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

            // City Landing Pages
            $cityPages = CityLandingPage::active()->sorted()->get(['city_slug', 'updated_at']);
            foreach ($cityPages as $cityPage) {
                $urls[] = [
                    'loc' => route('city.landing', $cityPage->city_slug),
                    'lastmod' => $cityPage->updated_at->toW3cString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.8',
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
