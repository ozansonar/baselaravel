<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GSC arama sorgusu metrikleri — "kullanıcı ne arattı" verisi.
 * Cron her 6 saatte son 28 günü çeker, var olan satırları upsert eder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gsc_query_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('query', 500);
            $table->date('date_from');
            $table->date('date_to');
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('ctr', 5, 2)->default(0);
            $table->decimal('position', 5, 2)->default(0);
            $table->string('country', 8)->default('tur');
            $table->string('device', 16)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['date_from', 'date_to']);
            $table->index('clicks');
            $table->index('position');
            $table->index('query');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gsc_query_metrics');
    }
};
