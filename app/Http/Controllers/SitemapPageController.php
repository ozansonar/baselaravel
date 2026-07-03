<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SitemapPageService;
use Illuminate\View\View;

/**
 * HTML site haritası — /site-haritasi
 * Kullanıcı dostu, kategorize linkler. SEO topic-siloing bonusu.
 */
final class SitemapPageController extends Controller
{
    public function __construct(
        private readonly SitemapPageService $sitemapPageService,
    ) {}

    public function __invoke(): View
    {
        return view('site-haritasi', [
            'groups' => $this->sitemapPageService->buildGroups(),
        ]);
    }
}
