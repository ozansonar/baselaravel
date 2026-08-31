<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Panelden yönetilen adresler.
 *
 * Rotalar koda gömülüydü: yeni bir adres açmak ya da bir sayfaya ikinci bir
 * adres vermek geliştirici işiydi. Çok dilli bir sitede bu iki kat zor —
 * "iletisim" ve "contact" aynı sayfaya bakmalı ama her biri kendi dilinde.
 *
 * Tablo iki yönde de kullanılıyor: gelen isteği çözerken (bu slug hangi
 * rotaya gidiyor) ve giden bağlantıyı üretirken (bu rota bu dilde hangi
 * slug'la yazılıyor).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_routes', function (Blueprint $table): void {
            $table->id();

            // Dil boş bırakılabiliyor: "tüm diller" demek. Dil kodu kısa,
            // languages tablosundakiyle aynı genişlikte.
            $table->string('locale', 5)->nullable();
            $table->string('slug', 191);

            // Hedef, kod tarafında tanımlı bir rota adı. Serbest metin değil:
            // panelde açılır listeden seçiliyor, yazım hatası olamıyor.
            $table->string('target_route', 100);
            $table->json('target_params')->nullable();

            $table->string('type', 20)->default('render');
            $table->boolean('is_active')->default(true);

            // Yöneticinin kendine notu; ne için açtığını hatırlaması için.
            $table->string('note', 191)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Gelen istek her seferinde (dil, slug) ile aranıyor.
            $table->index(['locale', 'slug']);
            // Giden bağlantı üretilirken (rota, dil) ile aranıyor.
            $table->index(['target_route', 'locale']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_routes');
    }
};
