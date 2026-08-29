<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E-posta benzersizliği yalnız yaşayan kullanıcılar arasında geçerli olmalı.
 *
 * users.email düz bir UNIQUE taşıyordu ve bu kısıt silinmiş satırları da
 * sayıyordu: bir kullanıcı silindikten sonra aynı adresle yeniden kaydolamıyor,
 * veritabanı seviyesinde "Duplicate entry" ile düşüyordu. Oysa soft delete'in
 * anlamı satırın kayıtta kalması, adresin işgal edilmesi değil.
 *
 * Çözüm, kısıtı e-postanın kendisinden alıp yalnız yaşayan satırlarda dolu olan
 * bir üretilmiş sütuna taşımak: silinmiş satırda değer NULL oluyor ve NULL'lar
 * benzersizlik sayımında birbirinden ayrı sayıldığı için istediğiniz kadar
 * silinmiş kayıt aynı adresi taşıyabiliyor. Yaşayan satırlarda ise değer
 * e-postanın kendisi, yani ikinci bir canlı kullanıcı hâlâ reddediliyor.
 *
 * Kısmi indeks (WHERE deleted_at IS NULL) daha doğrudan bir anlatım olurdu ama
 * MySQL onu desteklemiyor; üretilmiş sütun hem MySQL/MariaDB'de hem SQLite'ta
 * çalışıyor. VIRTUAL seçildi: değer diske yazılmıyor, indeks üzerinden okunuyor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_email_unique');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('email_active')
                ->nullable()
                ->virtualAs('case when deleted_at is null then email end')
                ->after('email');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->unique('email_active', 'users_email_active_unique');
        });
    }

    /**
     * Geri alma, düz UNIQUE'i geri koyuyor.
     *
     * Bu göç yürürlükteyken silinmiş bir kullanıcının adresiyle yeni bir kayıt
     * açılmışsa geri alma o veriyle çakışır ve düşer — kısıt gerçekten
     * gevşetildiği için beklenen davranış bu. Geri almadan önce çakışan
     * satırların elle ayıklanması gerekir.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_email_active_unique');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('email_active');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->unique('email', 'users_email_unique');
        });
    }
};
