<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->index('user_id');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->index('product_id');
        });

        Schema::table('mail_logs', function (Blueprint $table): void {
            $table->index('to');
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->dropIndex(['user_id']);
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropIndex(['product_id']);
        });

        Schema::table('mail_logs', function (Blueprint $table): void {
            $table->dropIndex(['to']);
        });
    }
};
