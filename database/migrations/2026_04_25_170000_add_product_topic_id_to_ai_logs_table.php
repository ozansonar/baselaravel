<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modül 4 Faz 2 — Cron'un Topic Cluster havuzundan seçtiği topic'i log'a
 * işler. AI Log detayında hangi ProductTopic için yazıldığı izlenebilir
 * olur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_logs', function (Blueprint $table) {
            $table->foreignId('product_topic_id')
                ->nullable()
                ->after('city_name')
                ->constrained('product_topics')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_logs', function (Blueprint $table) {
            $table->dropForeign(['product_topic_id']);
            $table->dropColumn('product_topic_id');
        });
    }
};
