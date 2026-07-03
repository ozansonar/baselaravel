<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SpecialDay;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Tarih aralığındaki özel günleri döner — DB-first, gerekirse formül + AI fallback.
 *
 * Akış:
 *  1. DB'de o aralıkta kayıt varsa hepsini al
 *  2. Sabit (config-bazlı) günleri formülle hesapla, eksik olanları DB'ye seed
 *  3. Kural-bazlı günleri (Anneler 2.Pzr, Babalar 3.Pzr) hesapla, DB'ye seed
 *  4. Hicri günler için DB'de o yıl için AI kayıt yoksa kullanıcıya uyarı
 *     (otomatik AI tetiklemez — admin '/admin/ozel-gunler' sayfasından manuel çağırır)
 */
final class SpecialDaysProvider
{
    public function __construct(
        private readonly SpecialDaysAiService $aiService,
    ) {}

    /**
     * Verilen aralıktaki tüm özel günleri tarih sırasıyla döner.
     * Eksik olan sabit/kural günleri otomatik DB'ye eklenir.
     *
     * @return Collection<int, SpecialDay>
     */
    public function getDaysInRange(Carbon $from, Carbon $to): Collection
    {
        // Tarih aralığını normalize et — başlangıç sondan büyükse swap
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        // 1) Sabit + kural-bazlı günleri seed et (yoksa)
        $this->ensureFormulaBasedDaysExist($from, $to);

        // 2) DB'den o aralıktaki tüm günleri çek
        return SpecialDay::query()
            ->inRange($from, $to)
            ->orderBy('date')
            ->orderBy('name')
            ->get();
    }

    /**
     * DB'de hangi yıllar için hicri (AI kaynaklı) gün eksik olduğunu döner.
     * Kullanıcı admin paneli üzerinden eksik yıllar için "AI'dan getir" butonuna basar.
     *
     * @return list<int>
     */
    public function getYearsMissingHicriData(Carbon $from, Carbon $to): array
    {
        $missing = [];

        for ($year = $from->year; $year <= $to->year; $year++) {
            $hasAi = SpecialDay::query()
                ->forYear($year)
                ->where('source', SpecialDay::SOURCE_AI)
                ->exists();

            if (! $hasAi) {
                $missing[] = $year;
            }
        }

        return $missing;
    }

    /**
     * Sabit + kural-bazlı günleri DB'ye yazar (idempotent — varsa atlar).
     */
    public function ensureFormulaBasedDaysExist(Carbon $from, Carbon $to): void
    {
        for ($year = $from->year; $year <= $to->year; $year++) {
            $this->seedFixedDaysForYear($year);
            $this->seedRuleBasedDaysForYear($year);
        }
    }

    /**
     * Belirli bir yıl için tüm formül-bazlı günleri seed eder (sabit + kural).
     * Idempotent — aynı slug+date varsa atlar.
     */
    public function seedYearAllFormulas(int $year): int
    {
        $countBefore = SpecialDay::forYear($year)->count();
        $this->seedFixedDaysForYear($year);
        $this->seedRuleBasedDaysForYear($year);
        $countAfter = SpecialDay::forYear($year)->count();

        return $countAfter - $countBefore;
    }

    private function seedFixedDaysForYear(int $year): void
    {
        $fixed = (array) config('turkish-special-days.fixed', []);

        foreach ($fixed as $entry) {
            if (! is_array($entry) || empty($entry['md']) || empty($entry['name'])) {
                continue;
            }

            $date = sprintf('%04d-%s', $year, (string) $entry['md']);
            if (! $this->isValidDate($date)) {
                continue;
            }

            $name     = (string) $entry['name'];
            $slug     = Str::slug($name) . '-' . $year;
            $category = $this->normalizeCategory((string) ($entry['category'] ?? 'genel'));
            $theme    = (string) ($entry['theme'] ?? '');

            SpecialDay::query()->firstOrCreate(
                ['date' => $date, 'slug' => $slug],
                [
                    'name'       => $name,
                    'category'   => $category,
                    'theme'      => $theme,
                    'source'     => SpecialDay::SOURCE_FIXED,
                    'is_movable' => false,
                ],
            );
        }
    }

    private function seedRuleBasedDaysForYear(int $year): void
    {
        // Anneler Günü = Mayıs ayının 2. Pazarı
        $mothersDay = $this->nthWeekdayOfMonth($year, 5, 7, 2); // Pazar = 7 (ISO)
        if ($mothersDay !== null) {
            $this->upsertRuleDay(
                $mothersDay,
                'Anneler Günü',
                SpecialDay::CATEGORY_OZEL,
                'Anne emeği, anneye özel kahvaltı paketi (Mayıs ayının 2. Pazarı)',
            );
        }

        // Babalar Günü = Haziran ayının 3. Pazarı
        $fathersDay = $this->nthWeekdayOfMonth($year, 6, 7, 3);
        if ($fathersDay !== null) {
            $this->upsertRuleDay(
                $fathersDay,
                'Babalar Günü',
                SpecialDay::CATEGORY_OZEL,
                'Baba emeği, babaya özel kahvaltı (Haziran ayının 3. Pazarı)',
            );
        }
    }

    private function upsertRuleDay(string $date, string $name, string $category, string $theme): void
    {
        $year = (int) substr($date, 0, 4);
        $slug = Str::slug($name) . '-' . $year;

        SpecialDay::query()->firstOrCreate(
            ['date' => $date, 'slug' => $slug],
            [
                'name'       => $name,
                'category'   => $category,
                'theme'      => $theme,
                'source'     => SpecialDay::SOURCE_RULE,
                'is_movable' => true,
            ],
        );
    }

    /**
     * Verilen ayın N'inci belirtilen haftagününün tarihini döner (YYYY-MM-DD).
     *
     * @param int $weekdayIso 1=Pazartesi, 7=Pazar
     * @param int $occurrence 1=ilk, 2=ikinci, ...
     */
    private function nthWeekdayOfMonth(int $year, int $month, int $weekdayIso, int $occurrence): ?string
    {
        $first = Carbon::createFromDate($year, $month, 1);
        $found = 0;

        for ($day = 1; $day <= $first->daysInMonth; $day++) {
            $date = Carbon::createFromDate($year, $month, $day);
            if ((int) $date->format('N') === $weekdayIso) {
                $found++;
                if ($found === $occurrence) {
                    return $date->toDateString();
                }
            }
        }

        return null;
    }

    private function isValidDate(string $date): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }
        try {
            Carbon::createFromFormat('Y-m-d', $date);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function normalizeCategory(string $raw): string
    {
        $cleaned = mb_strtolower(trim($raw));
        return in_array($cleaned, SpecialDay::CATEGORIES, true) ? $cleaned : SpecialDay::CATEGORY_GENEL;
    }
}
