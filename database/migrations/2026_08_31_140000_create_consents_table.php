<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Çerez rızasının kaydı.
 *
 * Tarayıcıdaki çerez ziyaretçinin ne seçtiğini hatırlamaya yeter, ama KVKK'da
 * ispat yükü veri sorumlusunda: "bu kişi şu tarihte şuna izin verdi" diyebilmek
 * gerekiyor ve ziyaretçinin silebildiği bir çerez bunu kanıtlamaz. Bu tablo o
 * kaydın kendisi.
 *
 * `token` çerezle aynı değeri taşır: ziyaretçi tercihini değiştirdiğinde eski
 * satır silinmez, yenisi yazılır — rızanın geçmişi de kayıttır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consents', function (Blueprint $table): void {
            $table->id();

            // Çerezdeki değerle eşleşir; aynı ziyaretçinin kararları bununla
            // bir araya geliyor.
            $table->uuid('token')->index();

            // Verilen izinler. Zorunlu kategori de yazılıyor: kaydın neyi
            // kapsadığı sonradan yorumlanmasın, olduğu gibi dursun.
            $table->json('categories');

            // Metin değişirse eski rıza artık o metne verilmiş sayılmaz.
            $table->unsignedSmallInteger('version')->default(1);

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('url', 500)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['token', 'created_at']);
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
    }
};
