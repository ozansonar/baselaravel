<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Toplu Instagram post planlaması (Bulk Schedule) takip tablosu.
 *
 * - Bir kullanıcı CSV yüklediğinde 1 BulkImport kaydı oluşturulur
 * - Her satır için ProcessBulkImportRowJob queue'ya atılır
 * - Job'lar processed_rows / success_count / fail_count sayaçlarını günceller
 * - errors JSON'unda satır bazlı hata mesajları biriktirilir
 * - status: pending → processing → completed (veya partial_failed)
 *
 * Cleanup: 30 gün sonra eski BulkImport kayıtları otomatik silinebilir
 * (şimdilik manuel, sonra cron eklenir).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending'); // pending|processing|completed|partial_failed|failed
            $table->unsignedSmallInteger('total_rows')->default(0);
            $table->unsignedSmallInteger('processed_rows')->default(0);
            $table->unsignedSmallInteger('success_count')->default(0);
            $table->unsignedSmallInteger('fail_count')->default(0);
            $table->json('errors')->nullable(); // [{row: 3, message: "tarih geçersiz"}, ...]
            $table->string('zip_path', 512)->nullable(); // Yüklenen ZIP arşivi yolu
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_imports');
    }
};
