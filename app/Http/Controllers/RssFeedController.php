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
            'siteDesc' => $this->settingService->get('site_description', 'Modern, hızlı ve güvenilir kurumsal çözümler.'),
        ])->render();

        return response($content, 200)
            ->header('Content-Type', 'application/rss+xml; charset=utf-8');
    }
}
