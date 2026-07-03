<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiGeneratedImage;
use App\Models\AiLog;
use App\Models\Setting;
use App\Models\VertexGeneration;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * AI Maliyet Raporu — image (ai_generated_images + vertex_generations) + text (ai_logs)
 * cost_usd değerlerini birleştirir, dashboard ve /admin/ai-costs sayfası
 * için aggregate veri döner.
 *
 * KAYNAKLAR:
 *  - ai_generated_images.cost_usd  → Imagen + Gemini Image (her çağrı sabit fiyat)
 *  - vertex_generations.cost_usd   → Vertex AI Image (model bazlı sabit fiyat)
 *  - ai_logs.cost_usd              → Gemini text (token × rate, AiService::calculateCost)
 *
 * BÜTÇE TANIMI:
 *  Kullanıcı isteği: "sadece görsel üretimi paralı, bütçe aşılınca dur"
 *  → Bütçe = SADECE image cost (ai_generated_images + vertex_generations).
 *  → Text cost rapor amaçlı görünür ama bütçeye dahil değil.
 *  → block kontrolü sadece image generation'ı engeller.
 *
 * CACHE: 5 dk TTL (config/ai-pricing.php) — cron 5dk'da çalışıyor zaten,
 * dashboard senkron kalır.
 */
final class AiCostReportService
{
    private const string CACHE_PREFIX = 'ai-cost-report.';

    private function ttl(): int
    {
        return (int) (config('ai-pricing.cache_ttl_seconds') ?? 300);
    }

    private function cacheKey(string $suffix): string
    {
        return self::CACHE_PREFIX . $suffix;
    }

    public function flush(): void
    {
        // Cache key'leri prefix bazlı tek tek silmek pahalı; tüm AI cost
        // cache'lerini ttl ile yenilenmesine bırakıyoruz. Manuel flush gerekirse
        // cache:clear admin komutu çağrılabilir.
        Cache::forget($this->cacheKey('monthly-' . now()->format('Y-m')));
        Cache::forget($this->cacheKey('daily-' . now()->format('Y-m')));
        Cache::forget($this->cacheKey('budget-status'));
    }

    // ─────────────────────────────────────────────────────────────────
    //  TOPLAM HESAPLAR
    // ─────────────────────────────────────────────────────────────────

    /**
     * Belirli ay için toplam maliyet (image + text ayrı).
     *
     * @return array{image: float, text: float, total: float, count_image: int, count_text: int}
     */
    public function getMonthlyTotal(?CarbonImmutable $month = null): array
    {
        $month ??= CarbonImmutable::now()->startOfMonth();
        $key = $this->cacheKey('monthly-' . $month->format('Y-m'));

        return Cache::remember($key, $this->ttl(), function () use ($month) {
            $start = $month->startOfMonth();
            $end = $month->endOfMonth();

            $imageStats = AiGeneratedImage::query()
                ->whereBetween('created_at', [$start, $end])
                ->selectRaw('COALESCE(SUM(cost_usd), 0) AS total, COUNT(*) AS cnt')
                ->first();

            $vertexStats = VertexGeneration::query()
                ->completed()
                ->whereBetween('created_at', [$start, $end])
                ->selectRaw('COALESCE(SUM(cost_usd), 0) AS total, COUNT(*) AS cnt')
                ->first();

            $textStats = AiLog::query()
                ->whereBetween('created_at', [$start, $end])
                ->selectRaw('COALESCE(SUM(cost_usd), 0) AS total, COUNT(*) AS cnt')
                ->first();

            $imageCost = (float) ($imageStats->total ?? 0) + (float) ($vertexStats->total ?? 0);
            $textCost = (float) ($textStats->total ?? 0);

            return [
                'image'       => round($imageCost, 4),
                'text'        => round($textCost, 4),
                'total'       => round($imageCost + $textCost, 4),
                'count_image' => (int) ($imageStats->cnt ?? 0) + (int) ($vertexStats->cnt ?? 0),
                'count_text'  => (int) ($textStats->cnt ?? 0),
            ];
        });
    }

    /**
     * Geçen ayla bu ay arasındaki yüzdesel değişim (image+text toplam).
     */
    public function getMonthOverMonthChange(): array
    {
        $thisMonth = $this->getMonthlyTotal(CarbonImmutable::now()->startOfMonth());
        $lastMonth = $this->getMonthlyTotal(CarbonImmutable::now()->subMonth()->startOfMonth());

        $change = $lastMonth['total'] > 0
            ? round((($thisMonth['total'] - $lastMonth['total']) / $lastMonth['total']) * 100, 1)
            : null;

        return [
            'this_month' => $thisMonth['total'],
            'last_month' => $lastMonth['total'],
            'change_pct' => $change,
            'direction'  => $change === null ? 'na' : ($change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat')),
        ];
    }

    /**
     * Belirli ayın günlük breakdown'u (1-31 gün).
     *
     * @return list<array{date: string, image: float, text: float, total: float}>
     */
    public function getDailyBreakdown(?CarbonImmutable $month = null): array
    {
        $month ??= CarbonImmutable::now()->startOfMonth();
        $key = $this->cacheKey('daily-' . $month->format('Y-m'));

        return Cache::remember($key, $this->ttl(), function () use ($month) {
            $start = $month->startOfMonth();
            $end = $month->endOfMonth();

            $images = AiGeneratedImage::query()
                ->whereBetween('created_at', [$start, $end])
                ->selectRaw('DATE(created_at) AS d, COALESCE(SUM(cost_usd), 0) AS total')
                ->groupBy('d')
                ->pluck('total', 'd')
                ->all();

            $vertex = VertexGeneration::query()
                ->completed()
                ->whereBetween('created_at', [$start, $end])
                ->selectRaw('DATE(created_at) AS d, COALESCE(SUM(cost_usd), 0) AS total')
                ->groupBy('d')
                ->pluck('total', 'd')
                ->all();

            $texts = AiLog::query()
                ->whereBetween('created_at', [$start, $end])
                ->selectRaw('DATE(created_at) AS d, COALESCE(SUM(cost_usd), 0) AS total')
                ->groupBy('d')
                ->pluck('total', 'd')
                ->all();

            $days = [];
            for ($i = 0; $i < $month->daysInMonth; $i++) {
                $date = $start->addDays($i)->format('Y-m-d');
                $img = (float) ($images[$date] ?? 0) + (float) ($vertex[$date] ?? 0);
                $txt = (float) ($texts[$date] ?? 0);
                $days[] = [
                    'date'  => $date,
                    'image' => round($img, 4),
                    'text'  => round($txt, 4),
                    'total' => round($img + $txt, 4),
                ];
            }

            return $days;
        });
    }

    /**
     * Son N gün (default 7) — sparkline için günlük toplam dizisi.
     *
     * @return list<array{date: string, total: float}>
     */
    public function getLastNDaysTotals(int $days = 7): array
    {
        $days = max(1, min($days, 90));
        $start = CarbonImmutable::now()->subDays($days - 1)->startOfDay();
        $end = CarbonImmutable::now()->endOfDay();

        $images = AiGeneratedImage::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) AS d, COALESCE(SUM(cost_usd), 0) AS total')
            ->groupBy('d')
            ->pluck('total', 'd')
            ->all();

        $vertex = VertexGeneration::query()
            ->completed()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) AS d, COALESCE(SUM(cost_usd), 0) AS total')
            ->groupBy('d')
            ->pluck('total', 'd')
            ->all();

        $texts = AiLog::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) AS d, COALESCE(SUM(cost_usd), 0) AS total')
            ->groupBy('d')
            ->pluck('total', 'd')
            ->all();

        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->addDays($i)->format('Y-m-d');
            $img = (float) ($images[$date] ?? 0) + (float) ($vertex[$date] ?? 0);
            $txt = (float) ($texts[$date] ?? 0);
            $out[] = ['date' => $date, 'total' => round($img + $txt, 4)];
        }

        return $out;
    }

    /**
     * Provider bazında dağılım — pie chart için.
     *
     * @return array<string, array{cost: float, count: int}>
     */
    public function getProviderBreakdown(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from ??= now()->startOfMonth();
        $to   ??= now()->endOfMonth();

        $rows = AiGeneratedImage::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('provider, COALESCE(SUM(cost_usd), 0) AS cost, COUNT(*) AS cnt')
            ->groupBy('provider')
            ->get();

        $result = [];
        foreach ($rows as $r) {
            $result[(string) $r->provider] = [
                'cost'  => round((float) $r->cost, 4),
                'count' => (int) $r->cnt,
            ];
        }

        $vertexStats = VertexGeneration::query()
            ->completed()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('COALESCE(SUM(cost_usd), 0) AS cost, COUNT(*) AS cnt')
            ->first();

        if (($vertexStats->cnt ?? 0) > 0) {
            $result['vertex-ai'] = [
                'cost'  => round((float) $vertexStats->cost, 4),
                'count' => (int) $vertexStats->cnt,
            ];
        }

        // Text gen tek kategori (gemini-text)
        $textStats = AiLog::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('COALESCE(SUM(cost_usd), 0) AS cost, COUNT(*) AS cnt')
            ->first();

        if (($textStats->cnt ?? 0) > 0) {
            $result['gemini-text'] = [
                'cost'  => round((float) $textStats->cost, 4),
                'count' => (int) $textStats->cnt,
            ];
        }

        return $result;
    }

    /**
     * Source breakdown — bulk_import / blog / instagram_post / standalone.
     *
     * @return array<string, array{cost: float, count: int}>
     */
    public function getSourceBreakdown(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from ??= now()->startOfMonth();
        $to   ??= now()->endOfMonth();

        $rows = AiGeneratedImage::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('source, COALESCE(SUM(cost_usd), 0) AS cost, COUNT(*) AS cnt')
            ->groupBy('source')
            ->get();

        $result = [];
        foreach ($rows as $r) {
            $key = (string) ($r->source ?? 'unknown');
            $result[$key] = [
                'cost'  => round((float) $r->cost, 4),
                'count' => (int) $r->cnt,
            ];
        }

        $vertexStats = VertexGeneration::query()
            ->completed()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('COALESCE(SUM(cost_usd), 0) AS cost, COUNT(*) AS cnt')
            ->first();

        if (($vertexStats->cnt ?? 0) > 0) {
            $result['vertex_image'] = [
                'cost'  => round((float) $vertexStats->cost, 4),
                'count' => (int) $vertexStats->cnt,
            ];
        }

        // Text gen kaynakları (AiLog.source enum)
        $textRows = AiLog::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('source, COALESCE(SUM(cost_usd), 0) AS cost, COUNT(*) AS cnt')
            ->groupBy('source')
            ->get();

        foreach ($textRows as $r) {
            $sourceValue = $r->source instanceof \BackedEnum
                ? $r->source->value
                : (string) ($r->source ?? 'unknown');
            $key = 'text_' . $sourceValue;
            $result[$key] = [
                'cost'  => round((float) $r->cost, 4),
                'count' => (int) $r->cnt,
            ];
        }

        return $result;
    }

    /**
     * En pahalı tek tek çağrılar — abuse/anomaly tespiti için.
     *
     * @return list<array{type: string, model: string, cost: float, created_at: string, link: string|null}>
     */
    public function getTopExpensive(int $limit = 10, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $from ??= now()->startOfMonth();
        $to   ??= now()->endOfMonth();
        $limit = max(1, min($limit, 50));

        $images = AiGeneratedImage::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('cost_usd')
            ->orderByDesc('cost_usd')
            ->limit($limit)
            ->get(['id', 'model', 'cost_usd', 'created_at']);

        $vertexImages = VertexGeneration::query()
            ->completed()
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('cost_usd')
            ->orderByDesc('cost_usd')
            ->limit($limit)
            ->get(['id', 'model', 'cost_usd', 'created_at']);

        $texts = AiLog::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('cost_usd')
            ->orderByDesc('cost_usd')
            ->limit($limit)
            ->get(['id', 'used_model', 'cost_usd', 'created_at']);

        $combined = [];
        foreach ($images as $i) {
            $combined[] = [
                'type'       => 'image',
                'model'      => (string) $i->model,
                'cost'       => round((float) $i->cost_usd, 6),
                'created_at' => $i->created_at?->format('d.m.Y H:i') ?? '',
                'link'       => null,
                'id'         => $i->id,
            ];
        }
        foreach ($vertexImages as $v) {
            $combined[] = [
                'type'       => 'vertex',
                'model'      => (string) $v->model,
                'cost'       => round((float) $v->cost_usd, 6),
                'created_at' => $v->created_at?->format('d.m.Y H:i') ?? '',
                'link'       => null,
                'id'         => $v->id,
            ];
        }
        foreach ($texts as $t) {
            $combined[] = [
                'type'       => 'text',
                'model'      => (string) $t->used_model,
                'cost'       => round((float) $t->cost_usd, 6),
                'created_at' => $t->created_at?->format('d.m.Y H:i') ?? '',
                'link'       => null,
                'id'         => $t->id,
            ];
        }

        usort($combined, fn ($a, $b) => $b['cost'] <=> $a['cost']);

        return array_slice($combined, 0, $limit);
    }

    // ─────────────────────────────────────────────────────────────────
    //  BÜTÇE
    // ─────────────────────────────────────────────────────────────────

    /**
     * Aylık bütçe limiti (Setting → fallback config).
     */
    public function getMonthlyBudget(): float
    {
        return (float) (Setting::getValue('ai_monthly_budget_usd', null)
            ?? config('ai-pricing.defaults.monthly_budget_usd', 50));
    }

    public function getAlertThresholdPercent(): int
    {
        return (int) (Setting::getValue('ai_budget_alert_threshold', null)
            ?? config('ai-pricing.defaults.alert_threshold_percent', 80));
    }

    public function isBlockEnabled(): bool
    {
        $val = Setting::getValue('ai_budget_block_when_exceeded', null);
        if ($val === null) {
            return (bool) config('ai-pricing.defaults.block_when_exceeded', true);
        }

        return (string) $val === '1';
    }

    /**
     * Bütçe durumu — dashboard widget + block mekanizması için.
     *
     * NOT: Bütçe sadece IMAGE cost'unu kapsıyor (kullanıcı kararı:
     * "sadece görsel üretimi paralı"). Text cost rapor amaçlı görünür.
     *
     * @return array{
     *     used_image: float, used_text: float, used_total: float,
     *     limit: float, percent: int, state: string,
     *     block_enabled: bool, exceeded: bool, alert: bool,
     *     remaining: float
     * }
     */
    public function budgetStatus(): array
    {
        $key = $this->cacheKey('budget-status');

        return Cache::remember($key, 60, function () { // 1dk cache (block check için kritik)
            $monthly = $this->getMonthlyTotal();
            $limit = $this->getMonthlyBudget();
            $usedImage = (float) $monthly['image'];
            $usedText = (float) $monthly['text'];
            $usedTotal = (float) $monthly['total'];

            // Bütçe sadece image cost'a uygulanır
            $percent = $limit > 0 ? (int) min(999, round(($usedImage / $limit) * 100)) : 0;
            $threshold = $this->getAlertThresholdPercent();
            $exceeded = $usedImage >= $limit;
            $alert = $percent >= $threshold;

            $state = match (true) {
                $exceeded                => 'exceeded',
                $alert                   => 'warning',
                default                  => 'ok',
            };

            return [
                'used_image'    => round($usedImage, 4),
                'used_text'     => round($usedText, 4),
                'used_total'    => round($usedTotal, 4),
                'limit'         => round($limit, 2),
                'percent'       => $percent,
                'state'         => $state,
                'block_enabled' => $this->isBlockEnabled(),
                'exceeded'      => $exceeded,
                'alert'         => $alert,
                'remaining'     => round(max(0, $limit - $usedImage), 4),
            ];
        });
    }

    /**
     * Image generation çağırılmadan önce kontrol — bütçe aşıldıysa true.
     *
     * AiImageGenerator bunu generate/enhance başında çağırır; true ise
     * çağrı reddedilir, hata mesajı kullanıcıya döner.
     *
     * @return array{blocked: bool, message: string|null}
     */
    public function shouldBlockImageGeneration(): array
    {
        $status = $this->budgetStatus();

        if (! $status['block_enabled']) {
            return ['blocked' => false, 'message' => null];
        }
        if (! $status['exceeded']) {
            return ['blocked' => false, 'message' => null];
        }

        return [
            'blocked' => true,
            'message' => sprintf(
                'AI Görsel üretimi geçici olarak durduruldu. Aylık bütçe aşıldı: $%.2f / $%.2f. ' .
                'Admin → Ayarlar → AI bölümünden bütçeyi yükselt veya bekleyip ay başını bekle.',
                $status['used_image'],
                $status['limit'],
            ),
        ];
    }
}
