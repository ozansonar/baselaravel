<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mobil uygulamanın bildirim adresleri.
 *
 * Jeton cihaza ait, hesaba değil: aynı telefondan iki farklı hesaba
 * girildiğinde jeton ikinci hesaba geçmeli, yoksa bildirim eski kullanıcıya
 * gider. Bu yüzden benzersizlik `token` üzerinde — kullanıcı+jeton üzerinde
 * değil.
 *
 * Jeton benzersiz ve dizinli; sütun genişliği projenin varsayılanı olan 191
 * karakter (dizinlenebilen en uzun varchar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // 191: dizinlenebilen en uzun varchar (Schema::defaultStringLength).
            // FCM jetonları 160 karakter civarı; sınıra dayanan bir jeton
            // sessizce kırpılırsa bildirim hiç ulaşmıyor, o yüzden doğrulama
            // da aynı sayıyı söylüyor.
            $table->string('token')->unique();
            $table->string('platform', 20);
            $table->string('device_name', 100)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_tokens');
    }
};
