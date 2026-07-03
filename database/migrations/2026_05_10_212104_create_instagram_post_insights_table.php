<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instagram_post_insights', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instagram_post_id')->constrained('instagram_posts')->cascadeOnDelete();
            $table->timestamp('fetched_at')->index();
            $table->unsignedInteger('likes')->default(0);
            $table->unsignedInteger('comments')->default(0);
            $table->unsignedInteger('reach')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('saves')->default(0);
            $table->unsignedInteger('shares')->default(0);
            $table->unsignedInteger('plays')->default(0); // Reels
            $table->json('raw_response')->nullable();
            $table->timestamps();

            $table->unique(['instagram_post_id', 'fetched_at'], 'ipi_post_fetched_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_post_insights');
    }
};
