<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `pages.sections` sütunu kaldırılıyor — hiç bağlanmamış bir özelliğin kalıntısı.
 *
 * Sütun 2026-03-13'te eklendi ve o günden beri **hiçbir şey yazmadı, hiçbir şey
 * okumadı**: form alanı yok, denetleyicide işlenmiyor, ön yüz basmıyor. Kaldığı
 * tek yer panelin sayfa düzenleme ekranındaki bir gezinme bloğuydu — "Hikaye,
 * Değerler, Tarihçe…" başlıkları, var olmayan çapalara gidiyordu. Yani
 * yöneticinin gördüğü ama tıklayınca hiçbir şey olmayan altı bağlantı.
 *
 * Veri kaybı yok: temizlik anında bütün satırlarda değer `null`'dı.
 *
 * `down()` sütunu geri koyuyor — özellik bir gün gerçekten yapılırsa şema
 * hazır olsun; ama o gün geldiğinde işi bu sütunu eklemek değil, yazmak,
 * okumak ve ekranı çizmek olacak.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pages', 'sections')) {
            return;
        }

        Schema::table('pages', function (Blueprint $table): void {
            $table->dropColumn('sections');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('pages', 'sections')) {
            return;
        }

        Schema::table('pages', function (Blueprint $table): void {
            $table->json('sections')->nullable()->after('content');
        });
    }
};
