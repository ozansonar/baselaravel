<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\BlogService;
use App\Services\SettingService;
use Illuminate\Http\Response;

final class RssFeedController extends Controller
{
    public function __construct(
        private readonly BlogService $blogService,
        private readonly SettingService $settingService,
    ) {}

    public function __invoke(): Response
    {
        $posts = $this->blogService->latestPublished(20);

        $content = view('feed', [
            'posts'    => $posts,
            'siteName' => $this->settingService->get('site_name', config('app.name')),
            // Ayarlarda site açıklaması yoksa devreye giren metin de çevrilebilir
            // olmalı: sabit yazılmış Türkçe cümle İngilizce akışa da düşüyordu.
            'siteDesc' => $this->settingService->get('site_description', __('site.misc.site_description')),
        ])->render();

        return response($content, 200)
            ->header('Content-Type', 'application/rss+xml; charset=utf-8');
    }
}
