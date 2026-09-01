<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\ContentSecurityPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SecurityHeaders
{
    public function __construct(
        private readonly ContentSecurityPolicy $csp,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Nonce sayfa çizilmeden önce üretilmeli: satır içi betikler onu
        // Blade içinden okuyor. Ara katman yanıt yolunda çalışsa bile bu
        // satır istek yolunda kalıyor.
        if ($this->csp->enabled()) {
            $this->csp->nonce();
        }

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // X-XSS-Protection bilerek basılmıyor: güncel hiçbir tarayıcı onu
        // desteklemiyor ve bazı eski sürümlerde filtrenin kendisi XSS'i
        // kolaylaştırdığı için kaldırılması öneriliyor. Yerini CSP aldı.

        if ($this->csp->enabled()) {
            $response->headers->set(
                $this->csp->headerName(),
                $this->csp->header(forAdmin: $this->isAdminSurface($request)),
            );
        }

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    /**
     * Panel yüzeyi mi?
     *
     * Panel, zengin metin editörünün ihtiyaç duyduğu birkaç ek kaynağa izin
     * veriyor. Ziyaretçinin gördüğü sayfalara aynı izinleri vermek, kazanç
     * olmadan yüzeyi genişletirdi.
     */
    private function isAdminSurface(Request $request): bool
    {
        return $request->is('admin', 'admin/*');
    }
}
