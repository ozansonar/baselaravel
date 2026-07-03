<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Google\GoogleMerchantFeedService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;

/**
 * Admin → Google Merchant Feed yönetim sayfası.
 *
 * - Feed URL'ini gösterir (kopyalanabilir)
 * - Aktif ürün sayısı + sağlık kontrolü (eksik alanlı ürünler)
 * - "Cache temizle ve yeniden oluştur" butonu
 * - "XML'i önizle" butonu (yeni sekmede public URL açar)
 */
final class GoogleMerchantFeedController extends Controller
{
    public function __construct(
        private readonly GoogleMerchantFeedService $service,
    ) {}

    public function index(): View
    {
        // Cache yoksa bir defa oluştur ki "henüz hiç çekilmedi" durumu olmasın
        $this->service->getOrBuildXml();

        $products = $this->service->eligibleProducts();
        $meta     = $this->service->getMeta();

        // Her ürünü tek tek validate et (admin sayfada uyarı için)
        $issues = [];
        foreach ($products as $p) {
            $errors = $this->service->validateProduct($p);
            if ($errors !== []) {
                $issues[] = [
                    'id'     => $p->id,
                    'name'   => $p->name,
                    'errors' => $errors,
                ];
            }
        }

        return view('admin.google-merchant-feed.index', [
            'feedUrl'      => $this->service->feedUrl(),
            'products'     => $products,
            'totalCount'   => $products->count(),
            'validCount'   => $meta['valid'] ?? max($products->count() - count($issues), 0),
            'invalidCount' => count($issues),
            'issues'       => $issues,
            'generatedAt'  => $meta['generated_at'] ?? null,
        ]);
    }

    public function rebuild(): RedirectResponse
    {
        try {
            $this->service->rebuild();

            return back()->with('success', 'Feed cache temizlendi ve yeniden oluşturuldu.');
        } catch (Throwable $e) {
            return back()->with('error', 'Feed yeniden oluşturulamadı: ' . $e->getMessage());
        }
    }
}
