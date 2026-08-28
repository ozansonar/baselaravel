<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\BlogService;
use App\Services\GalleryService;
use App\Services\SliderService;
use Illuminate\View\View;

final class HomeController extends Controller
{
    /**
     * Ana sayfadaki galeri şeridinde gösterilen en fazla görsel sayısı.
     * Şerit bir önizleme; tamamı /galeri adresinde.
     */
    private const GALLERY_PREVIEW_LIMIT = 8;

    public function __construct(
        private readonly SliderService $sliderService,
        private readonly BlogService $blogService,
        private readonly GalleryService $galleryService,
    ) {}

    public function __invoke(): View
    {
        return view('home', [
            'sliders'   => $this->sliderService->allActive(),
            // Dört yazı: ilki öne çıkan geniş kart, kalan üçü ızgara.
            'blogPosts' => $this->blogService->latestPublished(4),
            // Servis zaten önbellekten dönüyor; şerit için ilk birkaçı alınıyor.
            'galleryPhotos' => $this->galleryService->activePhotos()
                ->filter(fn ($photo): bool => (bool) $photo->image)
                ->take(self::GALLERY_PREVIEW_LIMIT),
        ]);
    }
}
