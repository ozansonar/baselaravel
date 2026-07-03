<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hashtag havuzu — admin panelden manuel veya AI önerisinden eklenir.
 * AI caption üretirken bu havuzdaki aktif hashtag'ler context'e verilir.
 * Kullanıldıkça usage_count artar, last_used_at güncellenir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hashtag_pool', function (Blueprint $table) {
            $table->id();
            $table->string('tag', 100)->unique();
            $table->string('category', 32)->default('genel');
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'category']);
            $table->index('usage_count');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hashtag_pool');
    }
};
