<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->unsignedInteger('total_views')->default(0);
            $table->unsignedInteger('unique_visitors')->default(0);
            $table->unsignedInteger('bot_views')->default(0);
            $table->unsignedInteger('desktop_views')->default(0);
            $table->unsignedInteger('mobile_views')->default(0);
            $table->unsignedInteger('tablet_views')->default(0);
            $table->json('top_pages')->nullable();
            $table->json('top_referrers')->nullable();
            $table->json('top_browsers')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_daily_stats');
    }
};
