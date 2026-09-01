<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Tarayıcının bildirdiği politika ihlallerini toplar.
 *
 * İhlal raporu bir hata değil, bir sinyal: ya politika gerçekten kullanılan bir
 * kaynağı unutmuş (o zaman `config/security.php` düzeltilir) ya da sayfaya
 * olmaması gereken bir betik girmiş (o zaman asıl mesele budur). İkisini de
 * ancak raporlar görünür kılıyor.
 *
 * Uç bilinçli olarak dar: kimlik istemiyor — ihlali bildiren tarayıcı oturum
 * çerezi göndermiyor — ama bu yüzden herkese açık. Üç şey onu koruyor: hız
 * sınırı, gövde boyutu tavanı ve loga yalnız tanınan alanların yazılması.
 */
final class CspReportController extends Controller
{
    /** Tarayıcının gönderdiği gövde bu boyutu aşarsa okunmuyor. */
    private const MAX_BODY_BYTES = 16384;

    /** Loga yazılan alanlar; gerisi atılıyor. */
    private const KEPT_FIELDS = [
        'document-uri',
        'referrer',
        'violated-directive',
        'effective-directive',
        'blocked-uri',
        'source-file',
        'line-number',
        'status-code',
    ];

    public function __invoke(Request $request): Response
    {
        // Yanıt her hâlükârda 204: tarayıcıya ne söylediğimizin bir önemi yok,
        // ve hata döndürmek raporlamayı susturmaktan başka işe yaramaz.
        $noContent = response()->noContent();

        if (! $this->withinRateLimit($request)) {
            return $noContent;
        }

        $raw = $request->getContent();

        if ($raw === '' || strlen($raw) > self::MAX_BODY_BYTES) {
            return $noContent;
        }

        $payload = json_decode($raw, true);

        if (! is_array($payload)) {
            return $noContent;
        }

        // İki biçim dolaşımda: eski `report-uri` tek raporu "csp-report"
        // anahtarının altında gönderiyor, yeni `report-to` bir dizi gönderiyor.
        $report = is_array($payload['csp-report'] ?? null)
            ? $payload['csp-report']
            : $payload;

        $fields = [];

        foreach (self::KEPT_FIELDS as $field) {
            $value = $report[$field] ?? null;

            if ($value !== null && (is_scalar($value))) {
                // Adresler saldırganın yazdığı metni taşıyabiliyor; loga giren
                // her değer kırpılıyor ve kontrol karakterlerinden arınıyor.
                $fields[$field] = Str::limit(
                    (string) preg_replace('/[\x00-\x1F\x7F]/u', '', (string) $value),
                    500,
                );
            }
        }

        if ($fields === []) {
            return $noContent;
        }

        Log::warning('CSP ihlali bildirildi', $fields);

        return $noContent;
    }

    /**
     * Bozuk bir tarayıcı eklentisi ya da kasıtlı bir istemci saniyede yüzlerce
     * rapor gönderebiliyor; sınır olmadan log dosyası şişer.
     */
    private function withinRateLimit(Request $request): bool
    {
        $limit = (int) config('security.csp.report_rate_limit', 30);

        if ($limit <= 0) {
            return true;
        }

        $key = 'csp-report:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return false;
        }

        RateLimiter::hit($key, 60);

        return true;
    }
}
