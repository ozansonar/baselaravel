<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * İçerik sürümleri.
 *
 * Denetim izi "kim ne zaman ne değiştirdi" sorusunu cevaplıyor ama değişikliği
 * **geri döndüremiyor**. Yanlışlıkla silinen bir paragrafın tek karşılığı,
 * onu hatırlayan birinin yeniden yazması. Bu tablo o boşluğu kapatıyor: her
 * kayıtta içeriğin o anki hâli saklanıyor ve istenen sürüm geri yüklenebiliyor.
 *
 * Sürüm **dile bağlı**, dil grubuna değil. Bu projede her dil ayrı bir satır;
 * TR'yi geri almak EN'i de geri alsaydı, iki dili iki ayrı kişi düzenlediğinde
 * biri ötekinin işini silerdi. Polimorfik kimlik zaten tek bir dilin satırını
 * gösteriyor; `locale` sütunu ekranda süzmek ve okurken anlamak için var.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_revisions', function (Blueprint $table): void {
            $table->id();

            // Sınıf adı olduğu gibi yazılıyor (morph map yok) — content_files
            // tablosundaki desenin aynısı, iki tablo aynı dili konuşsun.
            $table->string('revisionable_type', 191);
            $table->unsignedBigInteger('revisionable_id');

            $table->string('locale', 5);

            // Kaydeden kişi. Hesabı silinirse sürüm kalıyor: içeriğin geçmişi,
            // onu yazanın hesabından bağımsız bir kayıt.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // İçeriğin o anki hâli. Yalnız config/revisions.php'de listelenen
            // alanlar; tablonun tamamı saklansaydı sayaçlar ve zaman damgaları
            // da geri yüklenirdi.
            $table->json('payload');

            $table->timestamps();
            $table->softDeletes();

            // Ekranın sorduğu tek soru: bu içeriğin bu dilindeki sürümler,
            // yeniden eskiye.
            $table->index(
                ['revisionable_type', 'revisionable_id', 'locale', 'id'],
                'content_revisions_target_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_revisions');
    }
};
