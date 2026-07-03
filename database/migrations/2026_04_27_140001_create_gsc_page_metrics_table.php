<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GSC sayfa bazlı metrikler — "hangi sayfa ne kadar trafik aldı".
 * page_type ile ürün/blog/şehir/sayfa ayrımı yapılır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gsc_page_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('page_url', 1000);
            $table->string('page_path', 500);
            $table->string('page_type', 32)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->date('date_from');
            $table->date('date_to');
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('ctr', 5, 2)->default(0);
            $table->decimal('position', 5, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('page_path');
            $table->index('page_type');
            $table->index(['page_type', 'related_id']);
            $table->index(['date_from', 'date_to']);
            $table->index('clicks');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gsc_page_metrics');
    }
};
