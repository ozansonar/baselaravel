<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kaydedilmeyi bekleyen ekler oturumdan veritabanına taşınıyor.
 *
 * Ekler forma değil kendi isteğine bindiği için on dosya seçildiğinde on istek
 * aynı anda gidiyor. Her istek oturumu baştan okuyup sonunda geri yazdığından
 * en son biten diğerlerinin kaydını eziyordu: kullanıcı on dosyanın da
 * yüklendiğini görüyor, taslağı kaydedince kampanyada üç ek buluyordu. Bekleyen
 * ek artık kendi satırı — iki yükleme birbirinin satırına dokunmuyor.
 *
 * Kampanyası olmayan satır "henüz kaydedilmedi" demek; token onu forma
 * bağlıyor, user_id ise başkasının bekleyen dosyasının iliştirilmesini
 * engelliyor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_attachments', function (Blueprint $table): void {
            $table->foreignId('campaign_id')->nullable()->change();
            $table->uuid('token')->nullable()->after('campaign_id');
            $table->foreignId('user_id')->nullable()->after('token')->constrained()->nullOnDelete();
            $table->index('token');
        });
    }

    public function down(): void
    {
        // Bekleyen satırların kampanyası yok; sütunlar kalkarken onlar da
        // gitmeli, yoksa campaign_id yeniden zorunlu yapılamaz.
        DB::table('campaign_attachments')->whereNull('campaign_id')->delete();

        Schema::table('campaign_attachments', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['token']);
            $table->dropColumn(['token', 'user_id']);
            $table->foreignId('campaign_id')->nullable(false)->change();
        });
    }
};
