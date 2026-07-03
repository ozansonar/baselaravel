<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CityLandingPageService;
use App\Services\InternalLinkService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CityLandingPageController extends Controller
{
    public function __construct(
        private readonly CityLandingPageService $service,
        private readonly InternalLinkService $internalLinkService,
    ) {}

    /**
     * Frontend: /{city_slug}-koy-urunleri
     */
    public function show(Request $request, string $slug): View
    {
        $page = $this->service->findActiveBySlug($slug);

        if ($page === null) {
            throw new NotFoundHttpException();
        }

        // Eager-load rating aggregates so the view can render stars without
        // triggering N+1 — spec requires star ratings on city-page product cards.
        $products = Product::active()
            ->sorted()
            ->with(['category', 'images'])
            ->withCount(['approvedReviews as reviews_count'])
            ->withAvg('approvedReviews as reviews_avg', 'rating')
            ->get();

        // Modül 8 — Şehir landing içeriğindeki ürün adlarını ürün sayfasına
        // linkler. AI/admin ürettiği content'i runtime'da zenginleştirir,
        // DB'yi kirletmez. Aynı ürün listesi zaten yüklü olduğundan ekstra
        // sorgu maliyeti yok.
        $renderedContent = $page->content
            ? $this->internalLinkService->injectProductLinks($page->content, $products)
            : null;

        return view('city-landing', [
            'page'            => $page,
            'products'        => $products,
            'renderedContent' => $renderedContent,
        ]);
    }
}
