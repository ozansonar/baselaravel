<?php

declare(strict_types=1);

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Gösterge panosundaki sayıların önbelleğini düşürür.
 *
 * Sayılar beş dakika önbellekte duruyor ama hiçbir yol onu temizlemiyordu:
 * yönetici iletişim mesajını okuyor, panoda "okunmamış" sayısı beş dakika
 * daha eski değeri yazıyordu — az önce yaptığı işle çelişen bir sayı.
 *
 * Temizlik tek yerde: sayıları besleyen dört model (kullanıcı, içerik, sayfa,
 * iletişim mesajı) bu gözlemciye bağlı. Servislerin tek tek hatırlaması
 * gerekseydi yeni bir yol eklendiğinde yine unutulurdu.
 *
 * Dikkat: sorgu kurucusu üzerinden yapılan toplu işlemler (whereIn()->delete()
 * gibi) model olayı doğurmuyor; o yollar önbelleği kendileri düşürüyor.
 */
final class DashboardStatsObserver
{
    public const CACHE_KEY = 'admin.dashboard.stats';

    public function saved(Model $model): void
    {
        $this->forget();
    }

    public function deleted(Model $model): void
    {
        $this->forget();
    }

    public function restored(Model $model): void
    {
        $this->forget();
    }

    public function forceDeleted(Model $model): void
    {
        $this->forget();
    }

    private function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
