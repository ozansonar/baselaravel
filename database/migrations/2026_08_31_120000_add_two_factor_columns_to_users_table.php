<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * İki adımlı doğrulama sütunları.
 *
 * Üç sütun, üçü de nullable: 2FA isteğe bağlı ve mevcut hesapların hiçbiri
 * göç anında kilitlenmemeli.
 *
 *   two_factor_secret            → TOTP anahtarı (şifreli saklanıyor)
 *   two_factor_recovery_codes    → telefonu kaybedene kalan yol (şifreli)
 *   two_factor_confirmed_at      → kurulum tamamlandı mı
 *
 * Anahtar üretilip ekranda QR gösterildiği anda değil, kullanıcı ilk doğru
 * kodu girdiğinde "açık" sayılıyor: aksi hâlde QR'ı okutmayı beceremeyen biri
 * kendi hesabından kilitlenirdi. Ayrımı tutan sütun confirmed_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
            ]);
        });
    }
};
