<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PageView;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Jenssegers\Agent\Agent;

class AnalyticsService
{
    /**
     * @return array{success: bool, error?: string, id?: int}
     */
    public function track(array $data): array
    {
        try {
            $ua = (string) ($data['user_agent'] ?? '');

            // jenssegers/agent yüklü değilse regex tabanlı fallback kullan.
            if (class_exists(Agent::class)) {
                $parsed = $this->parseWithAgent($ua);
            } else {
                $parsed = $this->parseWithFallback($ua);
            }

            $isBot = $parsed['is_bot'];
            $botName = $parsed['bot_name'];
            $deviceType = $parsed['device_type'];
            $browser = $parsed['browser'];
            $browserVersion = $parsed['browser_version'];
            $os = $parsed['os'];

            $referrer = $data['referrer'] ?? null;
            $referrerDomain = $referrer ? (parse_url($referrer, PHP_URL_HOST) ?: null) : null;

            // Ekran boyutu smallint unsigned (0-65535) — aşırı değerleri kırp.
            $screenWidth = $data['screen_width'] ?? null;
            $screenHeight = $data['screen_height'] ?? null;
            if ($screenWidth !== null) {
                $screenWidth = max(0, min(65535, (int) $screenWidth));
            }
            if ($screenHeight !== null) {
                $screenHeight = max(0, min(65535, (int) $screenHeight));
            }

            // session_id char(40) — boşsa veya uzunsa güvenli hale getir.
            $sessionId = (string) ($data['session_id'] ?? '');
            if ($sessionId === '') {
                $sessionId = substr(hash('sha1', uniqid('anon_', true)), 0, 40);
            } else {
                $sessionId = mb_substr($sessionId, 0, 40);
            }

            $view = PageView::create([
                'url'             => mb_substr((string) ($data['url'] ?? ''), 0, 500),
                'url_path'        => mb_substr((string) ($data['path'] ?? '/'), 0, 191),
                'ip_address'      => mb_substr((string) ($data['ip'] ?? ''), 0, 45),
                'ip_masked'       => false,
                'user_agent'      => (string) ($data['user_agent'] ?? ''),
                'device_type'     => $deviceType,
                'browser'         => $browser ? mb_substr($browser, 0, 50) : null,
                'browser_version' => $browserVersion ? mb_substr((string) $browserVersion, 0, 20) : null,
                'os'              => $os ? mb_substr($os, 0, 50) : null,
                'referrer'        => $referrer ? mb_substr($referrer, 0, 500) : null,
                'referrer_domain' => $referrerDomain ? mb_substr($referrerDomain, 0, 100) : null,
                'session_id'      => $sessionId,
                'user_id'         => $data['user_id'] ?? null,
                'is_bot'          => $isBot,
                'bot_name'        => $botName ? mb_substr($botName, 0, 50) : null,
                'screen_width'    => $screenWidth,
                'screen_height'   => $screenHeight,
                'viewed_at'       => $data['viewed_at'] ?? now(),
            ]);

            return ['success' => true, 'id' => $view->id];
        } catch (\Throwable $e) {
            // ERROR seviyesine çek — sessiz başarısızlık yerine storage/logs/laravel.log'da hemen görünsün.
            Log::error('Analytics tracking failed', [
                'error'      => $e->getMessage(),
                'exception'  => get_class($e),
                'file'       => $e->getFile(),
                'line'       => $e->getLine(),
                'url'        => $data['url'] ?? null,
                'ip'         => $data['ip'] ?? null,
                'session_id' => $data['session_id'] ?? null,
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getStats(Carbon $from, Carbon $to, bool $excludeBots = true): array
    {
        $cacheKey = 'analytics.stats.' . md5($from->toIso8601String() . $to->toIso8601String() . ($excludeBots ? '1' : '0'));

        return Cache::remember($cacheKey, 60, function () use ($from, $to, $excludeBots): array {
            $base = PageView::betweenDates($from, $to);
            $humans = (clone $base)->where('is_bot', false);
            $bots = (clone $base)->where('is_bot', true);

            $totalViews = $excludeBots ? $humans->count() : $base->count();
            $uniqueVisitors = $humans->distinct('session_id')->count('session_id');
            $botViews = $bots->count();
            $days = max(1, $from->diffInDays($to) + 1);

            return [
                'total_views'        => $totalViews,
                'unique_visitors'    => $uniqueVisitors,
                'bot_views'          => $botViews,
                'avg_daily_views'    => (int) round($totalViews / $days),
            ];
        });
    }

    public function getDailyChart(Carbon $from, Carbon $to, bool $excludeBots = true): array
    {
        $cacheKey = 'analytics.daily_chart.' . md5($from->toDateString() . $to->toDateString() . ($excludeBots ? '1' : '0'));

        return Cache::remember($cacheKey, 60, function () use ($from, $to, $excludeBots): array {
            $query = PageView::query()
                ->selectRaw('DATE(viewed_at) as date, COUNT(*) as count')
                ->betweenDates($from, $to)
                ->groupBy('date')
                ->orderBy('date');

            if ($excludeBots) {
                $query->where('is_bot', false);
            }

            $rows = $query->pluck('count', 'date')->all();

            $labels = [];
            $data = [];
            $cursor = $from->copy()->startOfDay();
            $end = $to->copy()->startOfDay();

            while ($cursor->lte($end)) {
                $key = $cursor->toDateString();
                $labels[] = $cursor->format('d M');
                $data[] = (int) ($rows[$key] ?? 0);
                $cursor->addDay();
            }

            return ['labels' => $labels, 'data' => $data];
        });
    }

    public function getTopPages(Carbon $from, Carbon $to, int $limit = 10, bool $excludeBots = true): array
    {
        $cacheKey = 'analytics.top_pages.' . md5($from->toDateString() . $to->toDateString() . $limit . ($excludeBots ? '1' : '0'));

        return Cache::remember($cacheKey, 60, function () use ($from, $to, $limit, $excludeBots): array {
            $query = PageView::query()
                ->selectRaw('url_path, COUNT(*) as count')
                ->betweenDates($from, $to)
                ->groupBy('url_path')
                ->orderByDesc('count')
                ->limit($limit);

            if ($excludeBots) {
                $query->where('is_bot', false);
            }

            return $query->get()
                ->map(fn ($row) => ['path' => $row->url_path, 'count' => (int) $row->count])
                ->all();
        });
    }

    public function getDeviceBreakdown(Carbon $from, Carbon $to): array
    {
        return $this->groupBreakdown('device_type', $from, $to, excludeBots: true);
    }

    public function getBrowserBreakdown(Carbon $from, Carbon $to, int $limit = 8): array
    {
        return $this->groupBreakdown('browser', $from, $to, excludeBots: true, limit: $limit);
    }

    public function getReferrerBreakdown(Carbon $from, Carbon $to, int $limit = 10): array
    {
        $cacheKey = 'analytics.referrers.' . md5($from->toDateString() . $to->toDateString() . $limit);

        return Cache::remember($cacheKey, 60, function () use ($from, $to, $limit): array {
            $rows = PageView::query()
                ->selectRaw("COALESCE(referrer_domain, 'direct') as source, COUNT(*) as count")
                ->where('is_bot', false)
                ->betweenDates($from, $to)
                ->groupBy('source')
                ->orderByDesc('count')
                ->limit($limit)
                ->get();

            return $rows->map(fn ($row) => ['label' => $row->source, 'count' => (int) $row->count])->all();
        });
    }

    public function getBotActivity(Carbon $from, Carbon $to, int $limit = 10): array
    {
        $cacheKey = 'analytics.bots.' . md5($from->toDateString() . $to->toDateString() . $limit);

        return Cache::remember($cacheKey, 60, function () use ($from, $to, $limit): array {
            $rows = PageView::query()
                ->selectRaw("COALESCE(bot_name, 'Unknown') as bot, COUNT(*) as count")
                ->where('is_bot', true)
                ->betweenDates($from, $to)
                ->groupBy('bot')
                ->orderByDesc('count')
                ->limit($limit)
                ->get();

            return $rows->map(fn ($row) => ['label' => $row->bot, 'count' => (int) $row->count])->all();
        });
    }

    public function getRecentVisits(int $limit = 20, bool $excludeBots = true): Collection
    {
        $query = PageView::query()
            ->orderByDesc('viewed_at')
            ->limit($limit);

        if ($excludeBots) {
            $query->where('is_bot', false);
        }

        return $query->get();
    }

    /**
     * Clear all analytics-related cache keys. Safe for both Redis and file drivers.
     */
    public function flushCache(): void
    {
        // Brute-force: cache:clear gibi davranmak yerine bilinen prefix'leri tek tek temizle.
        // Driver tag desteği olmayabilir; o yüzden tüm cache'i flush etmek en güvenlisi.
        try {
            Cache::flush();
        } catch (\Throwable $e) {
            Log::warning('Analytics cache flush failed', ['error' => $e->getMessage()]);
        }
    }

    public function paginateVisits(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        $query = PageView::query()->orderByDesc('viewed_at');

        if (isset($filters['from'])) {
            $query->where('viewed_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }
        if (isset($filters['to'])) {
            $query->where('viewed_at', '<=', Carbon::parse($filters['to'])->endOfDay());
        }
        if (array_key_exists('is_bot', $filters) && $filters['is_bot'] !== null && $filters['is_bot'] !== '') {
            $query->where('is_bot', (bool) $filters['is_bot']);
        }
        if (!empty($filters['device_type'])) {
            $query->where('device_type', $filters['device_type']);
        }
        if (!empty($filters['url'])) {
            $query->where('url_path', 'like', '%' . $filters['url'] . '%');
        }

        return $query->paginate($perPage)->withQueryString();
    }

    private function groupBreakdown(string $column, Carbon $from, Carbon $to, bool $excludeBots = true, ?int $limit = null): array
    {
        $cacheKey = 'analytics.breakdown.' . $column . '.' . md5($from->toDateString() . $to->toDateString() . ($excludeBots ? '1' : '0') . ($limit ?? 'all'));

        return Cache::remember($cacheKey, 60, function () use ($column, $from, $to, $excludeBots, $limit): array {
            $query = PageView::query()
                ->selectRaw("COALESCE($column, 'unknown') as label, COUNT(*) as count")
                ->betweenDates($from, $to)
                ->groupBy('label')
                ->orderByDesc('count');

            if ($excludeBots) {
                $query->where('is_bot', false);
            }
            if ($limit) {
                $query->limit($limit);
            }

            return $query->get()
                ->map(fn ($row) => ['label' => (string) $row->label, 'count' => (int) $row->count])
                ->all();
        });
    }

    public function clearCache(): void
    {
        // Redis tag desteği olmadığı için key'ler kendiliğinden 5 dakika içinde expire olur.
        // Manuel temizlik gerekirse cache:clear kullanılabilir.
    }

    /**
     * @return array{is_bot: bool, bot_name: ?string, device_type: string, browser: ?string, browser_version: ?string, os: ?string}
     */
    private function parseWithAgent(string $ua): array
    {
        $agent = new Agent();
        $agent->setUserAgent($ua);

        $isBot = $agent->isRobot();
        $botName = $isBot ? ($agent->robot() ?: 'Unknown Bot') : null;

        if ($isBot) {
            $deviceType = 'bot';
        } elseif ($agent->isTablet()) {
            $deviceType = 'tablet';
        } elseif ($agent->isMobile()) {
            $deviceType = 'mobile';
        } elseif ($agent->isDesktop()) {
            $deviceType = 'desktop';
        } else {
            $deviceType = 'other';
        }

        $browser = $agent->browser() ?: null;
        $browserVersion = $browser ? ($agent->version($browser) ?: null) : null;
        $platform = $agent->platform() ?: null;
        $platformVersion = $platform ? ($agent->version($platform) ?: null) : null;
        $os = $platform ? trim($platform . ($platformVersion ? ' ' . $platformVersion : '')) : null;

        return [
            'is_bot'          => $isBot,
            'bot_name'        => $botName,
            'device_type'     => $deviceType,
            'browser'         => $browser,
            'browser_version' => $browserVersion ? (string) $browserVersion : null,
            'os'              => $os,
        ];
    }

    /**
     * Jenssegers/agent paketi yoksa basit regex ile UA parse eder.
     *
     * @return array{is_bot: bool, bot_name: ?string, device_type: string, browser: ?string, browser_version: ?string, os: ?string}
     */
    private function parseWithFallback(string $ua): array
    {
        $uaLower = strtolower($ua);

        $botPatterns = [
            'googlebot'    => 'Googlebot',
            'bingbot'      => 'Bingbot',
            'yandexbot'    => 'YandexBot',
            'baiduspider'  => 'Baiduspider',
            'duckduckbot'  => 'DuckDuckBot',
            'applebot'     => 'Applebot',
            'facebookexternalhit' => 'FacebookBot',
            'twitterbot'   => 'Twitterbot',
            'linkedinbot'  => 'LinkedInBot',
            'whatsapp'     => 'WhatsApp',
            'telegrambot'  => 'TelegramBot',
            'ahrefsbot'    => 'AhrefsBot',
            'semrushbot'   => 'SemrushBot',
            'mj12bot'      => 'MJ12bot',
            'dotbot'       => 'DotBot',
            'petalbot'     => 'PetalBot',
            'gptbot'       => 'GPTBot',
            'claudebot'    => 'ClaudeBot',
            'anthropic'    => 'AnthropicBot',
            'ccbot'        => 'CCBot',
            'perplexitybot' => 'PerplexityBot',
        ];

        $isBot = false;
        $botName = null;

        foreach ($botPatterns as $needle => $name) {
            if (str_contains($uaLower, $needle)) {
                $isBot = true;
                $botName = $name;
                break;
            }
        }

        // Generic bot/crawler/spider kelimeleri — yukarıdaki listede yoksa yakala.
        if (! $isBot && preg_match('/(bot|crawler|spider|crawl)/i', $ua)) {
            $isBot = true;
            $botName = 'Unknown Bot';
        }

        if ($isBot) {
            $deviceType = 'bot';
        } elseif (str_contains($uaLower, 'ipad') || (str_contains($uaLower, 'tablet') && ! str_contains($uaLower, 'mobile'))) {
            $deviceType = 'tablet';
        } elseif (preg_match('/(mobile|iphone|ipod|android.*mobile|blackberry|windows phone)/i', $ua)) {
            $deviceType = 'mobile';
        } elseif ($ua !== '') {
            $deviceType = 'desktop';
        } else {
            $deviceType = 'other';
        }

        $browser = null;
        $browserVersion = null;

        $browserRules = [
            'Edge'    => '/Edg(?:e|A|iOS)?\/([0-9.]+)/i',
            'Opera'   => '/(?:Opera|OPR)\/([0-9.]+)/i',
            'Firefox' => '/Firefox\/([0-9.]+)/i',
            'Chrome'  => '/Chrome\/([0-9.]+)/i',
            'Safari'  => '/Version\/([0-9.]+).*Safari/i',
            'IE'      => '/MSIE ([0-9.]+)|Trident.*rv:([0-9.]+)/i',
        ];

        foreach ($browserRules as $name => $pattern) {
            if (preg_match($pattern, $ua, $m)) {
                $browser = $name;
                $browserVersion = $m[1] ?? ($m[2] ?? null);
                break;
            }
        }

        $os = null;

        if (preg_match('/Windows NT ([0-9.]+)/i', $ua, $m)) {
            $winMap = ['10.0' => '10', '6.3' => '8.1', '6.2' => '8', '6.1' => '7', '6.0' => 'Vista', '5.1' => 'XP'];
            $os = 'Windows ' . ($winMap[$m[1]] ?? $m[1]);
        } elseif (preg_match('/Mac OS X ([0-9_\.]+)/i', $ua, $m)) {
            $os = 'macOS ' . str_replace('_', '.', $m[1]);
        } elseif (preg_match('/Android ([0-9.]+)/i', $ua, $m)) {
            $os = 'Android ' . $m[1];
        } elseif (preg_match('/(?:iPhone OS|CPU OS) ([0-9_]+)/i', $ua, $m)) {
            $os = 'iOS ' . str_replace('_', '.', $m[1]);
        } elseif (str_contains($uaLower, 'linux')) {
            $os = 'Linux';
        }

        return [
            'is_bot'          => $isBot,
            'bot_name'        => $botName,
            'device_type'     => $deviceType,
            'browser'         => $browser,
            'browser_version' => $browserVersion,
            'os'              => $os,
        ];
    }
}
