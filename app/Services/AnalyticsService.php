<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PageView;
use App\Support\CacheKeys;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Support\LikeSearch;

class AnalyticsService
{
    public function __construct(
        private readonly UserAgentParser $userAgents,
        private readonly CachePurger $cache,
    ) {}

    /**
     * @return array{success: bool, error?: string, id?: int}
     */
    public function track(array $data): array
    {
        try {
            $ua = (string) ($data['user_agent'] ?? '');

            // Ayrıştırma UserAgentParser'da: aynı okuma "Cihazlarım" ekranında
            // da gerekiyor ve iki kopya zamanla iki farklı sonuç verirdi.
            $parsed = $this->userAgents->parse($ua);

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

        return $this->cache->rememberWithin(CacheKeys::PREFIX_ANALYTICS, $cacheKey, 60, function () use ($from, $to, $excludeBots): array {
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

        return $this->cache->rememberWithin(CacheKeys::PREFIX_ANALYTICS, $cacheKey, 60, function () use ($from, $to, $excludeBots): array {
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

        return $this->cache->rememberWithin(CacheKeys::PREFIX_ANALYTICS, $cacheKey, 60, function () use ($from, $to, $limit, $excludeBots): array {
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

        return $this->cache->rememberWithin(CacheKeys::PREFIX_ANALYTICS, $cacheKey, 60, function () use ($from, $to, $limit): array {
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

        return $this->cache->rememberWithin(CacheKeys::PREFIX_ANALYTICS, $cacheKey, 60, function () use ($from, $to, $limit): array {
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
        // Üye adı listede görünüyor; ilişki önden yüklenmezse satır başına
        // ayrı sorgu açılır.
        $query = PageView::query()
            ->with('user')
            ->orderByDesc('viewed_at')
            ->limit($limit);

        if ($excludeBots) {
            $query->where('is_bot', false);
        }

        return $query->get();
    }

    /**
     * Analitik önbelleğini düşürür — yalnız onu.
     *
     * Eskiden burada `Cache::flush()` vardı: analitik ekranındaki tek bir
     * yenileme ayarları, çevirileri, site haritasını, dil listesini ve bütün
     * ön yüz içerik önbelleğini birlikte siliyordu. Varsayılan sürücü
     * veritabanı olduğu için yeniden ısınmanın bedelini de ilk ziyaretçiler
     * ödüyordu.
     *
     * Anahtarların hepsi ortak bir önek taşıyor; silinen küme o önekle sınırlı.
     */
    public function flushCache(): void
    {
        $this->cache->forgetPrefix(CacheKeys::PREFIX_ANALYTICS);
    }

    /**
     * Default window for "who is on the site right now".
     *
     * A view is recorded once per page load, so someone reading a long article
     * stops producing hits. Five minutes is the usual compromise: long enough
     * that a reader does not blink out, short enough to still mean "now".
     */
    public const ACTIVE_WINDOW_MINUTES = 5;

    /**
     * Sessions seen in the last few minutes, with the page each one is on.
     *
     * One row per visitor rather than per hit: the latest view of each session
     * is the page they are looking at, and the rest of the session tells us how
     * long they have been around and how much they have read.
     *
     * @return Collection<int, object>
     */
    public function getActiveVisitors(int $windowMinutes = self::ACTIVE_WINDOW_MINUTES, bool $excludeBots = true): Collection
    {
        $since = now()->subMinutes($windowMinutes);

        // The newest hit of each active session. Portable across MySQL and
        // SQLite, unlike a window function.
        $latestIds = PageView::query()
            ->selectRaw('MAX(id) as id')
            ->where('viewed_at', '>=', $since)
            ->when($excludeBots, fn ($q) => $q->where('is_bot', false))
            ->groupBy('session_id')
            ->pluck('id');

        if ($latestIds->isEmpty()) {
            return collect();
        }

        $current = PageView::query()
            ->with('user:id,first_name,last_name,email')
            ->whereIn('id', $latestIds)
            ->orderByDesc('viewed_at')
            ->get();

        // Session totals in one query instead of one per visitor.
        $sessions = $current->pluck('session_id')->all();

        $totals = PageView::query()
            ->selectRaw('session_id, COUNT(*) as page_count, MIN(viewed_at) as started_at, MIN(id) as first_id')
            ->whereIn('session_id', $sessions)
            ->groupBy('session_id')
            ->get()
            ->keyBy('session_id');

        // Where the visitor came from is a property of how the session started,
        // not of the page they happen to be on now — by the second click the
        // referrer is this site itself.
        $entries = PageView::query()
            ->whereIn('id', $totals->pluck('first_id')->filter())
            ->get(['id', 'session_id', 'url_path', 'referrer_domain'])
            ->keyBy('session_id');

        return $current->map(function (PageView $view) use ($totals, $entries): object {
            $total = $totals->get($view->session_id);
            $entry = $entries->get($view->session_id);
            $startedAt = $total?->started_at ? Carbon::parse($total->started_at) : $view->viewed_at;

            return (object) [
                'id'              => $view->id,
                'session_id'      => $view->session_id,
                'url_path'        => $view->url_path,
                'url'             => $view->url,
                'ip_address'      => $view->ip_address,
                'ip_masked'       => (bool) $view->ip_masked,
                'device_type'     => $view->device_type,
                'browser'         => $view->browser,
                'os'              => $view->os,
                'referrer_domain' => $entry?->referrer_domain ?? $view->referrer_domain,
                'entry_path'      => $entry?->url_path ?? $view->url_path,
                'is_bot'          => (bool) $view->is_bot,
                'bot_name'        => $view->bot_name,
                'user'            => $view->user?->only(['id', 'first_name', 'last_name', 'email']),
                'viewed_at'       => $view->viewed_at,
                'seconds_ago'     => (int) max(0, $view->viewed_at->diffInSeconds(now())),
                'page_count'      => (int) ($total->page_count ?? 1),
                'session_seconds' => (int) max(0, $startedAt->diffInSeconds($view->viewed_at, false)),
            ];
        });
    }

    /**
     * How many distinct sessions are active right now.
     */
    public function getOnlineCount(int $windowMinutes = self::ACTIVE_WINDOW_MINUTES, bool $excludeBots = true): int
    {
        return (int) PageView::query()
            ->where('viewed_at', '>=', now()->subMinutes($windowMinutes))
            ->when($excludeBots, fn ($q) => $q->where('is_bot', false))
            ->distinct()
            ->count('session_id');
    }

    /**
     * The page-by-page feed, newest first.
     *
     * Passing the highest id already on screen returns only what happened
     * since, so the panel can append instead of redrawing the whole list.
     *
     * @return Collection<int, PageView>
     */
    public function getLiveFeed(int $limit = 30, ?int $afterId = null, bool $excludeBots = true): Collection
    {
        return PageView::query()
            ->with('user:id,first_name,last_name')
            ->when($excludeBots, fn ($q) => $q->where('is_bot', false))
            ->when($afterId !== null, fn ($q) => $q->where('id', '>', $afterId))
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Which pages the active visitors are on, busiest first.
     *
     * @return array<int, array{label: string, count: int}>
     */
    public function getActivePages(int $windowMinutes = self::ACTIVE_WINDOW_MINUTES, int $limit = 10): array
    {
        $latestIds = PageView::query()
            ->selectRaw('MAX(id) as id')
            ->where('viewed_at', '>=', now()->subMinutes($windowMinutes))
            ->where('is_bot', false)
            ->groupBy('session_id')
            ->pluck('id');

        if ($latestIds->isEmpty()) {
            return [];
        }

        return PageView::query()
            ->selectRaw('url_path as label, COUNT(*) as count')
            ->whereIn('id', $latestIds)
            ->groupBy('url_path')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => ['label' => (string) $row->label, 'count' => (int) $row->count])
            ->all();
    }

    /**
     * Ziyaret listesinin tanıdığı süzgeç anahtarları.
     *
     * Ekran da dışa aktarma da bu listeyi okur; iki yerde ayrı yazılsaydı
     * dosyaya inen ile ekranda görünen zamanla ayrışırdı.
     *
     * @return list<string>
     */
    public function visitFilterKeys(): array
    {
        return ['url', 'is_bot', 'device_type', 'browser', 'os', 'referrer', 'visitor', 'from', 'to', 'sort'];
    }

    public function paginateVisits(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        return $this->visitQuery($filters)->paginate($perPage)->withQueryString();
    }

    /**
     * Ziyaret kaydı listesinin sorgusu.
     *
     * Sayfalama ile sayım aynı süzgeçten geçmeli; iki yerde ayrı kurulunca
     * ekrandaki "kaç kayıt" ile listedeki kayıtlar birbirini tutmaz.
     *
     * @param  array<string, mixed> $filters
     * @return \Illuminate\Database\Eloquent\Builder<PageView>
     */
    public function visitQuery(array $filters)
    {
        // Üye adı sütunda görünüyor; ilişki önden yüklenmezse her satır için
        // ayrı sorgu açılır.
        $query = PageView::query()->with('user');

        if (! empty($filters['from'])) {
            $query->where('viewed_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }

        if (! empty($filters['to'])) {
            $query->where('viewed_at', '<=', Carbon::parse($filters['to'])->endOfDay());
        }

        if (array_key_exists('is_bot', $filters) && $filters['is_bot'] !== null && $filters['is_bot'] !== '') {
            $query->where('is_bot', (bool) $filters['is_bot']);
        }

        if (! empty($filters['device_type'])) {
            $query->where('device_type', $filters['device_type']);
        }

        if (! empty($filters['browser'])) {
            $query->where('browser', $filters['browser']);
        }

        if (! empty($filters['os'])) {
            $query->where('os', $filters['os']);
        }

        if (! empty($filters['referrer'])) {
            // "direct" bir alan adı değil, kaynağı olmayan ziyaretin adı.
            $filters['referrer'] === 'direct'
                ? $query->whereNull('referrer_domain')
                : $query->where('referrer_domain', $filters['referrer']);
        }

        match ($filters['visitor'] ?? '') {
            'member' => $query->whereNotNull('user_id'),
            'guest'  => $query->whereNull('user_id'),
            default  => null,
        };

        if (! empty($filters['url'])) {
            // Arama yalnızca yolu değil, IP ve oturumu da kapsıyor: "şu adres
            // ne gezdi" sorusu bu ekranda sorulur.
            //
            // Joker karakterler düz metin sayılıyor, yoksa "%" yazan biri süzgeç
            // yaptığını sanarak tüm listeye bakar. Kaçış karakteri ESCAPE ile
            // açıkça bildiriliyor: MySQL ters bölüyü kendiliğinden kaçış sayar,
            // SQLite saymaz — belirtilmezse kaçırılan "%" hiçbir şey bulmaz.
            $term = LikeSearch::term((string) $filters['url']);

            $query->where(function ($inner) use ($term): void {
                foreach (['url_path', 'ip_address', 'session_id'] as $index => $column) {
                    $index === 0
                        ? $inner->whereRaw(LikeSearch::clause($column), [$term])
                        : $inner->orWhereRaw(LikeSearch::clause($column), [$term]);
                }
            });
        }

        return ($filters['sort'] ?? '') === 'oldest'
            ? $query->orderBy('viewed_at')->orderBy('id')
            : $query->orderByDesc('viewed_at')->orderByDesc('id');
    }

    /**
     * Süzgeç kutularını dolduran değerler.
     *
     * Tarayıcı ve işletim sistemi listesi veriden geliyor; elle yazılan bir
     * liste yeni bir tarayıcı çıktığında eksik kalırdı. Sayım pahalı olduğu
     * için önbelleğe alınıyor.
     *
     * @return array{browsers: array<int, string>, systems: array<int, string>, referrers: array<int, string>}
     */
    public function visitFilterOptions(): array
    {
        return $this->cache->rememberWithin(CacheKeys::PREFIX_ANALYTICS, 'analytics.visit_filter_options', 600, function (): array {
            $column = function (string $column): array {
                return PageView::query()
                    ->select($column)
                    ->whereNotNull($column)
                    ->where($column, '!=', '')
                    ->groupBy($column)
                    ->orderByRaw('COUNT(*) DESC')
                    ->limit(20)
                    ->pluck($column)
                    ->all();
            };

            return [
                'browsers'  => $column('browser'),
                'systems'   => $column('os'),
                'referrers' => $column('referrer_domain'),
            ];
        });
    }

    private function groupBreakdown(string $column, Carbon $from, Carbon $to, bool $excludeBots = true, ?int $limit = null): array
    {
        $cacheKey = 'analytics.breakdown.' . $column . '.' . md5($from->toDateString() . $to->toDateString() . ($excludeBots ? '1' : '0') . ($limit ?? 'all'));

        return $this->cache->rememberWithin(CacheKeys::PREFIX_ANALYTICS, $cacheKey, 60, function () use ($column, $from, $to, $excludeBots, $limit): array {
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
}
