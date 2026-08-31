<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PwaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Siteyi telefona kurulabilir yapan üç adres.
 *
 * Üçü de kök dizinde: servis çalışanının kapsamı bulunduğu dizinle sınırlı,
 * `/js/sw.js` altından sunulsaydı yalnız `/js/` altını görebilirdi.
 *
 * PWA kapalıyken üçü de 404: kapalı bir özelliğin adresleri açık kalırsa
 * tarayıcı eski servis çalışanını çalıştırmaya devam eder.
 */
final class PwaController extends Controller
{
    public function __construct(
        private readonly PwaService $pwa,
    ) {}

    /**
     * GET /site.webmanifest
     */
    public function manifest(): JsonResponse
    {
        $this->ensureEnabled();

        return response()->json($this->pwa->manifest(), 200, [
            'Content-Type'  => 'application/manifest+json',
            'Cache-Control' => 'public, max-age=3600',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * GET /sw.js
     *
     * Önbelleklenmiyor: tarayıcı bu dosyanın değişip değişmediğini anlamak
     * için ona bakıyor ve önbellekten okunan bir kopya, yeni sürümün hiç
     * kurulmaması demek olurdu.
     */
    public function serviceWorker(): Response
    {
        $this->ensureEnabled();

        return response($this->pwa->serviceWorker(), 200, [
            'Content-Type'          => 'application/javascript; charset=utf-8',
            'Cache-Control'         => 'no-cache, must-revalidate',
            'Service-Worker-Allowed' => '/',
        ]);
    }

    /**
     * GET /offline
     *
     * Kurulum sırasında önbelleğe alınıyor; ağ yokken gezinilen her adres
     * buraya düşüyor.
     */
    public function offline(): View
    {
        return view('offline');
    }

    private function ensureEnabled(): void
    {
        if (! $this->pwa->isEnabled()) {
            throw new NotFoundHttpException();
        }
    }
}
