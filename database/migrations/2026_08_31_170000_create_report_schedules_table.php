<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zamanlanmış raporlar.
 *
 * Yönetici raporu her ay elle üretip indirmek yerine "her pazartesi bana
 * e-postala" diyebiliyor. Tanım burada duruyor; üretimi cron'daki
 * `reports:dispatch` yapıyor.
 *
 * `last_run_at` ve `last_error` kayıtta: gönderilmeyen bir raporun neden
 * gönderilmediği, kimsenin bakmadığı bir log dosyasında değil, tanımın
 * yanında görünmeli.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_schedules', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 30);
            $table->string('frequency', 20);
            $table->string('range', 20)->default('30');
            $table->string('format', 10)->default('excel');
            // Alıcılar JSON: bir rapor birden çok kişiye gidebiliyor ve ayrı
            // tablo, tek alanlık bir ilişki için fazla.
            $table->json('recipients');
            $table->boolean('is_active')->default(true);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('last_run_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'frequency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
    }
};
