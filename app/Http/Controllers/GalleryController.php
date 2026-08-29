<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\GalleryType;
use App\Services\GalleryCategoryService;
use App\Services\GalleryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class GalleryController extends Controller
{
    public function __construct(
        private readonly GalleryService $galleryService,
        private readonly GalleryCategoryService $galleryCategoryService,
    ) {}

    public function __invoke(Request $request): View
    {
        $categories = $this->galleryCategoryService->allActive();

        // Adres çubuğundan gelen değer doğrudan kullanılmıyor: var olan bir
        // kategoriye karşılık gelmiyorsa yok sayılıyor. Böylece uydurma bir
        // slug boş bir galeri değil, "tümü" görünümünü veriyor ve süzgeç
        // çubuğu hiçbir zaman ekranda olmayan bir seçimi işaretlemiyor.
        $categorySlug = $request->query('kategori');
        $categorySlug = is_string($categorySlug) && $categories->contains('slug', $categorySlug)
            ? $categorySlug
            : null;

        $type = $request->query('tur');
        $type = is_string($type) && GalleryType::tryFrom($type) !== null ? $type : null;

        $items = $this->galleryService->paginateActive($categorySlug, $type);

        return view('gallery.index', [
            'items'          => $items,
            'canonical'      => $this->canonicalUrl($categorySlug, $type, $items->currentPage()),
            'categories'     => $categories,
            'categorySlug'   => $categorySlug,
            'activeCategory' => $categorySlug === null ? null : $categories->firstWhere('slug', $categorySlug),
            'type'           => $type,
        ]);
    }

    /**
     * Sayfanın kendini gösteren adresi.
     *
     * Adresteki ham süzgeçten değil, kabul edilenlerden kuruluyor: tanınmayan
     * bir kategori yok sayılıp bütün galeri basılıyor, o adres kendini
     * gösterseydi aynı içerik uydurulabilecek her slug için ayrı bir adres
     * olarak dizine girerdi.
     */
    private function canonicalUrl(?string $categorySlug, ?string $type, int $page): string
    {
        return route('gallery', array_filter([
            'kategori' => $categorySlug,
            'tur'      => $type,
            'page'     => $page > 1 ? $page : null,
        ]));
    }
}
