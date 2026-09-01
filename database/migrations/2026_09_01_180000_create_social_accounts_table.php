<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bir hesabın bağlı olduğu sosyal kimlikler.
 *
 * Ayrı tablo, users'a iki sütun eklemek yerine: bir kişi hem Google hem Apple
 * ile girebiliyor ve ikisi de aynı hesap olmalı. Tek sütun olsaydı ikinci
 * sağlayıcı ya birinciyi ezerdi ya da ikinci bir hesap açardı.
 *
 * Eşleştirmenin gerçek anahtarı `provider_user_id` (jetondaki `sub`): e-posta
 * değişebiliyor, `sub` değişmiyor. E-posta yalnız kayıt için tutuluyor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->index();
            $table->string('provider', 20);
            $table->string('provider_user_id', 191);
            // Bağlandığı andaki adres; sağlayıcıda değişirse burası eskir ve
            // bu bilinçli — kimlik `sub`, adres yalnız kayıt.
            $table->string('email', 191)->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Aynı sosyal hesap iki kullanıcıya bağlanamaz: bağlanabilseydi
            // bir jetonun hangi hesabı açtığı belirsiz olurdu.
            $table->unique(['provider', 'provider_user_id']);
            $table->index(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
