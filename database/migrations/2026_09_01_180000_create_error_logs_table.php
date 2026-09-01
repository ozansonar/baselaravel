<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sunucu hataları için kalıcı kayıt.
 *
 * Hata bugüne kadar üç yere gidiyordu: Telegram, bildirim merkezi ve
 * `storage/logs/laravel.log`. Üçünün de aynı boşluğu vardı — ilk ikisi on
 * dakikalık kısma yüzünden **tekrarları hiç göstermiyor**, üçüncüsü ise panelden
 * okunamıyor. Sonuç: yönetici "bir hata olmuş" bilgisine ulaşıyor ama kaç kez
 * olduğunu, nereden geldiğini ve düzelip düzelmediğini göremiyordu.
 *
 * Tablo hata **başına** tek satır tutuyor, isteğe göre değil: aynı kusur binlerce
 * kez tekrar edip listeyi boğmasın diye parmak izi (tür + dosya + satır) benzersiz.
 * Her tekrarda sayaç artıyor ve son görülme bilgisi tazeleniyor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_logs', function (Blueprint $table): void {
            $table->id();

            // Tür + dosya + satırın özeti. Benzersiz: aynı kusur tek satır.
            $table->char('fingerprint', 32)->unique();

            $table->string('exception');
            $table->text('message')->nullable();
            $table->string('file');
            $table->unsignedInteger('line')->default(0);

            // Yığın izi kırpılmadan saklanıyor; `text` uzun izlerde taşıyor.
            $table->longText('trace')->nullable();

            // Hatanın geldiği istek. Konsoldan gelen hatalarda üçü de boş.
            $table->string('url', 2048)->nullable();
            $table->string('method', 10)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();

            // "Düzelttim" işareti. Aynı hata yeniden geldiğinde kendiliğinden
            // kalkıyor — düzeldiği sanılan bir kusurun geri döndüğü görülmeli.
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Listenin varsayılan sırası: çözülmemişler önce, en son görülen üstte.
            $table->index(['resolved_at', 'last_seen_at']);
            $table->index('last_seen_at');
            $table->index('exception');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_logs');
    }
};
