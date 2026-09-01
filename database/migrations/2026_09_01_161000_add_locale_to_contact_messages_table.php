<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * İletişim mesajı hangi dildeki sayfadan gönderildi?
 *
 * Yanıt panelden yazılıyor ve panel Türkçeye sabit
 * (App\Http\Middleware\SetAdminLocale), dolayısıyla yanıt maili de Türkçe
 * gidiyordu — İngilizce sayfadan yazan ziyaretçiye bile. Ziyaretçinin dili
 * yalnız formun gönderildiği anda biliniyor, o yüzden orada saklanıyor.
 *
 * Eski satırlarda null: o mesajlar için dil hiç kaydedilmedi, uydurmak yerine
 * boş bırakılıyor; ContactMessageReplyMail bu durumda varsayılan dile düşüyor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table): void {
            $table->string('locale', 5)->nullable()->after('message')->index();
        });
    }

    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table): void {
            $table->dropIndex(['locale']);
            $table->dropColumn('locale');
        });
    }
};
