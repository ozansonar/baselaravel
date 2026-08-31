<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Eski analitik kayıtlarındaki işletim sistemi adlarını yeni biçime taşır.
 *
 * jenssegers/agent kaldırıldı; ayrıştırma artık kendi servisimizde ve aynı
 * tarayıcıya daha okunur adlar veriyor ("macOS 10.15.7" yerine
 * "OS X 10_15_7", "Android 14" yerine "AndroidOS 14").
 *
 * Eski satırlar dokunulmadan bırakılsaydı analitik ekranı aynı platformu iki
 * ayrı satır olarak gösterirdi — grafikte yaşanmamış bir bölünme.
 *
 * Bot satırlarındaki "Mozilla" tarayıcı adı da temizleniyor: paket, robotun
 * kimlik satırındaki ilk kelimeyi tarayıcı sanıyordu.
 */
return new class extends Migration
{
    public function up(): void
    {
        // "OS X 10_15_7" → "macOS 10.15.7"
        foreach (DB::table('page_views')->select('os')->whereNotNull('os')->distinct()->pluck('os') as $os) {
            $yeni = (string) $os;

            $yeni = preg_replace('/^OS X\b/', 'macOS', $yeni) ?? $yeni;
            $yeni = preg_replace('/^AndroidOS\b/', 'Android', $yeni) ?? $yeni;
            $yeni = str_replace('_', '.', $yeni);

            if ($yeni !== (string) $os) {
                DB::table('page_views')->where('os', $os)->update(['os' => $yeni]);
            }
        }

        // Robotun kimlik satırındaki ilk kelime tarayıcı değil.
        DB::table('page_views')->where('is_bot', true)->where('browser', 'Mozilla')->update(['browser' => null]);
    }

    /**
     * Geri alınabilir değil: eski adların hangi satırdan geldiği kayıtta
     * durmuyor ve "macOS 10.15.7" değerini "OS X 10_15_7"e çevirmek, o
     * satırların gerçekten paket tarafından yazıldığını varsaymak olurdu.
     */
    public function down(): void
    {
        // Bilinçli olarak boş.
    }
};
