<?php

declare(strict_types=1);

namespace App\Services\Google;

use App\Models\GoogleApiLog;
use App\Models\GscPageMetric;
use App\Models\GscQueryMetric;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Google Search Console API entegrasyonu.
 *
 * Hata yönetimi (plan dosyası 🚨 Hata Yönetimi):
 *   - 401 / 404      → cron durur, exception throw, log'a yazılır
 *   - 429 / 503 / 5xx → exponential backoff (60s, 120s, 240s) 3 deneme
 *   - timeout        → 3 deneme
 *   - tüm istekler google_api_logs'a yazılır
 *
 * Veri gecikmesi: Google son 2-3 günü sunmaz, biz son 28 günü çekerken
 * "bugün - 3 gün" tampon kullanırız (plan dosyası ⏱️ GSC Veri Gecikmesi).
 */
final class SearchConsoleService
{
    private const string API_BASE       = 'https://searchconsole.googleapis.com';
    private const string SCOPE_READONLY = 'https://www.googleapis.com/auth/webmasters.readonly';

    private const int MAX_RETRIES   = 3;
    private const int BASE_BACKOFF  = 60;
    private const int LAG_DAYS      = 3;
    private const int DEFAULT_LIMIT = 5000;

    public function __construct(
        private readonly GoogleAuthService $auth,
    ) {}

    public function isEnabled(): bool
    {
        $flag = Setting::getValue('google_gsc_enabled', '1');

        return $this->auth->isConfigured() && $flag === '1';
    }

    public function siteUrl(): string
    {
        $configured = (string) env('GSC_PROPERTY_URL', '');

        if ($configured !== '') {
            return rtrim($configured, '/') . '/';
        }

        return rtrim(config('app.url', 'https://orhanbabaninciftligi.com'), '/') . '/';
    }

    /**
     * Fetch top search queries for the property (last N days).
     *
     * @return array{from: string, to: string, rows: int}
     */
    public function fetchAndStoreQueries(int $days = 28, int $limit = self::DEFAULT_LIMIT): array
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException('Search Console entegrasyonu kapalı veya yapılandırılmamış');
        }

        $to   = now()->subDays(self::LAG_DAYS)->toDateString();
        $from = now()->subDays(self::LAG_DAYS + $days)->toDateString();

        $rows = $this->query([
            'startDate'  => $from,
            'endDate'    => $to,
            'dimensions' => ['query'],
            'rowLimit'   => $limit,
            'dataState'  => 'final',
        ]);

        $persisted = $this->persistQueries($rows, $from, $to);

        Cache::forget('gsc.top_queries.dashboard');

        return [
            'from' => $from,
            'to'   => $to,
            'rows' => $persisted,
        ];
    }

    /**
     * Fetch per-page performance for the property.
     *
     * @return array{from: string, to: string, rows: int}
     */
    public function fetchAndStorePages(int $days = 28, int $limit = self::DEFAULT_LIMIT): array
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException('Search Console entegrasyonu kapalı veya yapılandırılmamış');
        }

        $to   = now()->subDays(self::LAG_DAYS)->toDateString();
        $from = now()->subDays(self::LAG_DAYS + $days)->toDateString();

        $rows = $this->query([
            'startDate'  => $from,
            'endDate'    => $to,
            'dimensions' => ['page'],
            'rowLimit'   => $limit,
            'dataState'  => 'final',
        ]);

        $persisted = $this->persistPages($rows, $from, $to);

        Cache::forget('gsc.page_metrics.recent');

        return [
            'from' => $from,
            'to'   => $to,
            'rows' => $persisted,
        ];
    }

    /**
     * URL Inspection — return raw Google response so caller can use what's needed.
     * Cached 24 hours per URL (plan dosyası cache stratejisi).
     *
     * @return array<string, mixed>
     */
    public function inspectUrl(string $url): array
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException('Search Console entegrasyonu kapalı veya yapılandırılmamış');
        }

        $cacheKey = 'gsc.inspect.' . md5($url);

        /** @var array<string, mixed>|null $cached */
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $body = [
            'inspectionUrl' => $url,
            'siteUrl'       => $this->siteUrl(),
            'languageCode'  => 'tur',
        ];

        $response = $this->callApi('POST', '/v1/urlInspection/index:inspect', $body, 'urlInspection');

        Cache::put($cacheKey, $response, now()->addHours(24));

        return $response;
    }

    /**
     * Low-level: call /searchAnalytics/query for current property.
     *
     * @param  array<string, mixed>  $body
     * @return array<int, array<string, mixed>>
     */
    private function query(array $body): array
    {
        $endpoint = '/v1/sites/' . rawurlencode($this->siteUrl()) . '/searchAnalytics/query';
        $response = $this->callApi('POST', $endpoint, $body, 'searchAnalyticsQuery');

        $rows = $response['rows'] ?? [];

        return is_array($rows) ? $rows : [];
    }

    /**
     * Generic API call with retry + logging.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function callApi(string $method, string $path, array $body, string $operation): array
    {
        $token  = $this->auth->preferredAccessToken(self::SCOPE_READONLY);
        $url    = self::API_BASE . $path;
        $start  = microtime(true);
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                $http = Http::withToken($token)
                    ->timeout((int) env('GOOGLE_API_TIMEOUT', 30))
                    ->acceptJson();

                $response = $method === 'POST'
                    ? $http->asJson()->post($url, $body)
                    : $http->get($url, $body);

                $duration = (int) round((microtime(true) - $start) * 1000);

                if ($response->successful()) {
                    /** @var array<string, mixed> $json */
                    $json = (array) $response->json();

                    $this->log('success', $operation, $response->status(), null, $body, strlen((string) $response->body()), $duration);

                    return $json;
                }

                $isRetriable = in_array($response->status(), [429, 500, 502, 503, 504], true);
                if ($isRetriable && $attempt < self::MAX_RETRIES) {
                    sleep(self::BASE_BACKOFF * (2 ** ($attempt - 1)));
                    continue;
                }

                $this->log('failed', $operation, $response->status(), $response->body(), $body, null, $duration);

                throw new RuntimeException("GSC API hata: HTTP {$response->status()} — " . $response->body());
            } catch (Throwable $e) {
                if ($attempt < self::MAX_RETRIES && ! $e instanceof RuntimeException) {
                    sleep(self::BASE_BACKOFF * (2 ** ($attempt - 1)));
                    continue;
                }

                $duration = (int) round((microtime(true) - $start) * 1000);
                $this->log('failed', $operation, null, $e->getMessage(), $body, null, $duration);

                throw $e;
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function persistQueries(array $rows, string $from, string $to): int
    {
        if ($rows === []) {
            return 0;
        }

        return DB::transaction(function () use ($rows, $from, $to): int {
            GscQueryMetric::query()
                ->whereDate('date_from', $from)
                ->whereDate('date_to', $to)
                ->delete();

            $count = 0;
            $now   = now();
            $batch = [];

            foreach ($rows as $row) {
                $keys = (array) ($row['keys'] ?? []);
                $term = isset($keys[0]) ? mb_substr((string) $keys[0], 0, 500) : null;
                if ($term === null || $term === '') {
                    continue;
                }

                $batch[] = [
                    'query'       => $term,
                    'date_from'   => $from,
                    'date_to'     => $to,
                    'clicks'      => (int) ($row['clicks'] ?? 0),
                    'impressions' => (int) ($row['impressions'] ?? 0),
                    'ctr'         => round(((float) ($row['ctr'] ?? 0)) * 100, 2),
                    'position'    => round((float) ($row['position'] ?? 0), 2),
                    'country'     => 'tur',
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];

                if (count($batch) >= 500) {
                    GscQueryMetric::query()->insert($batch);
                    $count += count($batch);
                    $batch = [];
                }
            }

            if ($batch !== []) {
                GscQueryMetric::query()->insert($batch);
                $count += count($batch);
            }

            return $count;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function persistPages(array $rows, string $from, string $to): int
    {
        if ($rows === []) {
            return 0;
        }

        return DB::transaction(function () use ($rows, $from, $to): int {
            GscPageMetric::query()
                ->whereDate('date_from', $from)
                ->whereDate('date_to', $to)
                ->delete();

            $count = 0;
            $now   = now();
            $batch = [];

            foreach ($rows as $row) {
                $keys = (array) ($row['keys'] ?? []);
                $url  = isset($keys[0]) ? (string) $keys[0] : null;
                if ($url === null || $url === '') {
                    continue;
                }

                $path     = $this->extractPath($url);
                $resolved = $this->resolvePageType($path);

                $batch[] = [
                    'page_url'    => mb_substr($url, 0, 1000),
                    'page_path'   => mb_substr($path, 0, 500),
                    'page_type'   => $resolved['type'],
                    'related_id'  => $resolved['related_id'],
                    'date_from'   => $from,
                    'date_to'     => $to,
                    'clicks'      => (int) ($row['clicks'] ?? 0),
                    'impressions' => (int) ($row['impressions'] ?? 0),
                    'ctr'         => round(((float) ($row['ctr'] ?? 0)) * 100, 2),
                    'position'    => round((float) ($row['position'] ?? 0), 2),
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];

                if (count($batch) >= 500) {
                    GscPageMetric::query()->insert($batch);
                    $count += count($batch);
                    $batch = [];
                }
            }

            if ($batch !== []) {
                GscPageMetric::query()->insert($batch);
                $count += count($batch);
            }

            return $count;
        });
    }

    private function extractPath(string $url): string
    {
        $parts = parse_url($url);
        $path  = isset($parts['path']) ? (string) $parts['path'] : '/';

        return $path === '' ? '/' : $path;
    }

    /**
     * @return array{type: string, related_id: int|null}
     */
    private function resolvePageType(string $path): array
    {
        if ($path === '/' || $path === '') {
            return ['type' => GscPageMetric::TYPE_HOME, 'related_id' => null];
        }

        if (str_starts_with($path, '/urun/')) {
            $slug    = trim(substr($path, 6), '/');
            $product = DB::table('products')->where('slug', $slug)->whereNull('deleted_at')->first(['id']);

            return ['type' => GscPageMetric::TYPE_PRODUCT, 'related_id' => $product?->id];
        }

        if (str_starts_with($path, '/blog/')) {
            $segments = array_values(array_filter(explode('/', trim($path, '/'))));
            $slug     = $segments[count($segments) - 1] ?? null;

            if ($slug !== null) {
                $post = DB::table('blog_posts')->where('slug', $slug)->whereNull('deleted_at')->first(['id']);
                return ['type' => GscPageMetric::TYPE_BLOG, 'related_id' => $post?->id];
            }
        }

        if (preg_match('#^/([a-z0-9\-]+)-koy-urunleri/?$#', $path, $m) === 1) {
            $citySlug = $m[1];
            $city     = DB::table('city_landing_pages')->where('city_slug', $citySlug)->whereNull('deleted_at')->first(['id']);

            return ['type' => GscPageMetric::TYPE_CITY, 'related_id' => $city?->id];
        }

        if (str_starts_with($path, '/kategori/') || str_starts_with($path, '/urunler/')) {
            return ['type' => GscPageMetric::TYPE_CATEGORY, 'related_id' => null];
        }

        if (str_starts_with($path, '/sayfa/') || in_array($path, ['/hakkimizda', '/iletisim', '/sss', '/site-haritasi', '/teslimat-kosullari', '/gizlilik-politikasi'], true)) {
            return ['type' => GscPageMetric::TYPE_PAGE, 'related_id' => null];
        }

        return ['type' => GscPageMetric::TYPE_OTHER, 'related_id' => null];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function log(
        string $status,
        string $operation,
        ?int $httpCode,
        ?string $errorMessage,
        ?array $payload,
        ?int $responseSize,
        int $durationMs,
    ): void {
        try {
            GoogleApiLog::create([
                'service'         => GoogleApiLog::SERVICE_GSC,
                'operation'       => $operation,
                'status'          => $status,
                'http_code'       => $httpCode,
                'error_message'   => $errorMessage !== null ? mb_substr($errorMessage, 0, 2000) : null,
                'request_payload' => $payload,
                'response_size'   => $responseSize,
                'duration_ms'     => $durationMs,
            ]);
        } catch (Throwable $e) {
            Log::warning('GoogleApiLog write failed: ' . $e->getMessage());
        }
    }
}
