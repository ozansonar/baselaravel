<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kullanıcının e-posta tercihleri.
 *
 * Kullanıcı sütununda JSON yerine ayrı tablo: tercihin ne zaman değiştiği
 * kayıtta duruyor ("bu postayı istemediğimi söylemiştim" tartışmasının cevabı
 * burada) ve yeni bir tür eklemek göç değil satır ekleme meselesi oluyor.
 *
 * Satır yalnız kullanıcı varsayılandan saptığında yazılıyor; kaydı olmayan
 * herkes için enum'un kendi varsayılanı geçerli.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Bir kullanıcı, bir tür, tek satır: ikinci satır hangisinin
            // geçerli olduğunu belirsiz bırakırdı. Yumuşak silinen satır da
            // kısıtın içinde kaldığı için servis withTrashed() ile yazıyor —
            // silinmiş bir tercihin üstüne yeni satır atmaya çalışmak
            // benzersizlik hatası verirdi.
            $table->unique(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notification_preferences');
    }
};
