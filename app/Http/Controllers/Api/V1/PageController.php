<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PageDetailResource;
use App\Http\Resources\Api\V1\PageResource;
use App\Http\Responses\ApiResponse;
use App\Services\PageService;
use Illuminate\Http\JsonResponse;

/**
 * Panelden yayınlanan statik sayfalar.
 *
 * Mobil uygulamanın "Hakkımızda" ekranı ve —mağazaların yayın için şart
 * koştuğu— gizlilik politikası, KVKK ve kullanım koşulları metinleri buradan
 * geliyor. Metinler uygulamaya gömülseydi her düzeltme bir mağaza güncellemesi
 * bekleyecekti.
 */
final class PageController extends Controller
{
    public function __construct(
        private readonly PageService $pages,
    ) {}

    /**
     * GET /api/v1/pages
     *
     * Sayfalanmıyor: sayı doğası gereği küçük ve istemci menüyü tek seferde
     * kurmak istiyor.
     */
    public function index(): JsonResponse
    {
        return ApiResponse::success(PageResource::collection($this->pages->allPublished()));
    }

    /**
     * GET /api/v1/pages/{slug}
     *
     * Yayında olmayan ya da bulunmayan sayfa 404 — servis firstOrFail()
     * kullanıyor, hata zarfa {@see \App\Exceptions\ApiExceptionRenderer}
     * tarafından çevriliyor.
     */
    public function show(string $slug): JsonResponse
    {
        $page = $this->pages->findBySlug($slug);

        // Ekler ayrı bir sorguyla geliyor: liste ucu onları hiç sormuyor, tek
        // sayfa için bir sorgu fazladan atmak listeyi ağırlaştırmaktan ucuz.
        $page->loadMissing('files');

        return ApiResponse::success(PageDetailResource::make($page));
    }
}
