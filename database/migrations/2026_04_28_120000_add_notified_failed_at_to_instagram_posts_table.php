<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Failed bildirim takibi:
 *   - notified_failed_at: 3+ ardışık fail sonrası admin'e mail gönderildi mi?
 *     NULL ise mail gönderilmemiş, NOT NULL ise gönderilmiş (idempotency).
 *     Manuel "Yeniden Dene" sonrasında null'a çekilmeli (replay logic).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instagram_posts', function (Blueprint $table) {
            $table->timestamp('notified_failed_at')->nullable()->after('retry_count');
        });
    }

    public function down(): void
    {
        Schema::table('instagram_posts', function (Blueprint $table) {
            $table->dropColumn('notified_failed_at');
        });
    }
};
