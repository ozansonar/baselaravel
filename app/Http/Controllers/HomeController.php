<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\BlogService;
use App\Services\CategoryService;
use App\Services\ProductService;
use App\Services\SliderService;
use App\Services\GoogleReviewService;
use App\Services\TestimonialService;
use App\Services\YouTubeService;
use Illuminate\View\View;

final class HomeController extends Controller
{
    public function __construct(
        private readonly SliderService $sliderService,
        private readonly CategoryService $categoryService,
        private readonly ProductService $productService,
        private readonly BlogService $blogService,
        private readonly TestimonialService $testimonialService,
        private readonly GoogleReviewService $googleReviewService,
        private readonly YouTubeService $youtubeService,
    ) {}

    public function __invoke(): View
    {
        return view('home', [
            'sliders'          => $this->sliderService->allActive(),
            'categories'       => $this->categoryService->allActive(),
            'featuredProducts' => $this->productService->featured(6),
            'testimonials'     => $this->testimonialService->allActive(3),
            'blogPosts'        => $this->blogService->latestPublished(3),
            'googleReviews'    => $this->googleReviewService->getVisibleReviews(5),
            'googleStats'      => $this->googleReviewService->getStats(),
            'youtubeVideos'    => $this->youtubeService->getVisibleVideos(6),
        ]);
    }
}
