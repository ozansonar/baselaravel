<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;

/**
 * config/turkish-special-days.php verilerini yıla göre takvim olarak döner.
 *
 * Kullanım: Bulk import "Yıllık Özel Günler" preset'i — seçilen yıl için
 * sabit + hareketli özel günleri tarih sırasına göre listeler.
 */
final class TurkishSpecialDaysService
{
    /**
     * Verilen yıl için tüm özel günleri tarih sırasıyla döner.
     *
     * @return list<array{date: string, name: string, category: string, theme: string, weekday: string}>
     */
    public function getDaysForYear(int $year): array
    {
        $config = (array) config('turkish-special-days', []);

        $fixed   = (array) ($config['fixed'] ?? []);
        $movable = (array) (($config['movable'] ?? [])[$year] ?? []);

        $days = [];

        // Sabit günleri yıla göre genişlet (MM-DD → YYYY-MM-DD)
        foreach ($fixed as $entry) {
            if (! is_array($entry) || empty($entry['md']) || empty($entry['name'])) {
                continue;
            }
            $date = sprintf('%04d-%s', $year, (string) $entry['md']);
            if (! $this->isValidDate($date)) {
                continue;
            }
            $days[] = [
                'date'     => $date,
                'name'     => (string) $entry['name'],
                'category' => (string) ($entry['category'] ?? 'genel'),
                'theme'    => (string) ($entry['theme'] ?? ''),
                'weekday'  => $this->turkishWeekday($date),
            ];
        }

        // Hareketli günleri ekle
        foreach ($movable as $entry) {
            if (! is_array($entry) || empty($entry['date']) || empty($entry['name'])) {
                continue;
            }
            $date = (string) $entry['date'];
            if (! $this->isValidDate($date)) {
                continue;
            }
            $days[] = [
                'date'     => $date,
                'name'     => (string) $entry['name'],
                'category' => (string) ($entry['category'] ?? 'genel'),
                'theme'    => (string) ($entry['theme'] ?? ''),
                'weekday'  => $this->turkishWeekday($date),
            ];
        }

        // Tarih sırasına göre sırala
        usort($days, static fn (array $a, array $b): int => strcmp($a['date'], $b['date']));

        return $days;
    }

    /**
     * Verilen yıl için kayıtlı özel gün sayısı.
     */
    public function countForYear(int $year): int
    {
        return count($this->getDaysForYear($year));
    }

    /**
     * Hareketli günler için kayıtlı yıllar.
     *
     * @return list<int>
     */
    public function availableMovableYears(): array
    {
        $movable = (array) config('turkish-special-days.movable', []);
        $years   = array_map('intval', array_keys($movable));
        sort($years);

        return array_values($years);
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

    private function turkishWeekday(string $date): string
    {
        static $map = [
            'Monday'    => 'Pazartesi',
            'Tuesday'   => 'Salı',
            'Wednesday' => 'Çarşamba',
            'Thursday'  => 'Perşembe',
            'Friday'    => 'Cuma',
            'Saturday'  => 'Cumartesi',
            'Sunday'    => 'Pazar',
        ];

        try {
            $en = Carbon::createFromFormat('Y-m-d', $date)->format('l');

            return $map[$en] ?? '';
        } catch (\Throwable) {
            return '';
        }
    }
}
