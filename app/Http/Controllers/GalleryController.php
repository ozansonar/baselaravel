<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\GalleryCategoryService;
use App\Services\GalleryService;
use Illuminate\View\View;

final class GalleryController extends Controller
{
    public function __construct(
        private readonly GalleryService $galleryService,
        private readonly GalleryCategoryService $galleryCategoryService,
    ) {}

    public function __invoke(): View
    {
        $gallery = $this->galleryService->allActiveGrouped();

        return view('gallery.index', [
            'photos'     => $gallery['photos'],
            'videos'     => $gallery['videos'],
            'categories' => $this->galleryCategoryService->allActive(),
        ]);
    }
}
