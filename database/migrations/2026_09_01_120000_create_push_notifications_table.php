<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Panelden gönderilen push bildirimlerinin kaydı.
 *
 * Gönderilen bildirim kayda geçmezse iki soru cevapsız kalıyor: "bu duyuru
 * gitti mi" ve "kaç kişiye ulaştı". İkisi de gönderimden sonra soruluyor ve
 * o an bakılacak tek yer bu tablo.
 *
 * Sayaçlar satırda tutuluyor, cihaz başına ayrı kayıt açılmıyor: kampanyada
 * alıcı tablosu var çünkü tek tek adresler yeniden denenebiliyor, push'ta ise
 * ölü jeton zaten gönderim anında siliniyor — saklanacak bir "yeniden dene"
 * durumu yok.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_notifications', function (Blueprint $table): void {
            $table->id();

            // Gönderen yönetici. Hesap silinirse kayıt kalıyor: "bu duyuru
            // gitti mi" sorusunun cevabı, soranın hesabından bağımsız.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title', 120);
            $table->string('body', 500);

            // Bildirime tıklandığında uygulamanın açacağı yer. Boş olabilir:
            // her duyurunun gidecek bir sayfası yok.
            $table->string('link', 500)->nullable();

            $table->string('audience', 20);

            // Hedef seçimi: rol kimliği ya da kullanıcı kimliği. Türü hedefe
            // göre değiştiği için tek sütunda tutuluyor; ilişki kurulmuyor
            // çünkü hedef silinse bile gönderim kaydı anlamını koruyor.
            $table->unsignedBigInteger('audience_id')->nullable();

            $table->string('status', 20)->default('queued');

            // Gönderim sayaçları. `skipped`, taşıyıcı yapılandırılmamışken
            // atlanan cihazları sayıyor — sıfır olmayan bir değer, kurulumda
            // eksik bir ayar olduğunu söylüyor.
            $table->unsignedInteger('total_devices')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);

            // Gönderim nerede kaldı: cron parça parça ilerliyor ve bir sonraki
            // turda kaldığı yerden devam ediyor.
            $table->unsignedBigInteger('cursor')->default(0);

            $table->text('last_error')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Cron'un her turda sorduğu soru: bekleyen bildirim var mı?
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_notifications');
    }
};
