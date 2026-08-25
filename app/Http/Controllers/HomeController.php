<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\BlogService;
use App\Services\SliderService;
use Illuminate\View\View;

final class HomeController extends Controller
{
    public function __construct(
        private readonly SliderService $sliderService,
        private readonly BlogService $blogService,
    ) {}

    public function __invoke(): View
    {
        return view('home', [
            'sliders'   => $this->sliderService->allActive(),
            'blogPosts' => $this->blogService->latestPublished(3),
        ]);
    }
}
