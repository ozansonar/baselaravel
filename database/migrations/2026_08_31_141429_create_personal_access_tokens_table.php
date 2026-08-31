<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * API jetonları (Sanctum).
 *
 * Bir satır bir cihazın oturumu. `token` düz metin değil hash: veritabanını ele
 * geçiren biri jetonları kullanamasın diye — bu yüzden jeton üretildiği anda bir
 * kez dönüyor ve bir daha okunamıyor.
 *
 * `tokenable` polimorfik: bugün yalnız User jeton alıyor ama Sanctum'un
 * sözleşmesi bu ve ileride yönetici uygulaması ya da servis hesabı eklenirse
 * tablo değişmiyor.
 *
 * Süresi dolan satırlar `sanctum:prune-expired` ile temizleniyor
 * (routes/console.php); silinmezlerse tablo her girişte bir satır büyür.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            // tokenable_type + tokenable_id ve üzerine bir indeks.
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            // Temizleme görevi ve her jeton doğrulaması bu sütuna bakıyor.
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
