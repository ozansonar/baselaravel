<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_logs', function (Blueprint $table) {
            $table->id();
            $table->string('status', 30)->default('started');
            $table->string('product_name', 255)->nullable();
            $table->text('prompt')->nullable();
            $table->longText('raw_response')->nullable();
            $table->json('parsed_response')->nullable();
            $table->foreignId('blog_post_id')->nullable()->constrained('blog_posts')->nullOnDelete();
            $table->text('error_message')->nullable();
            $table->longText('error_details')->nullable();
            $table->unsignedInteger('api_duration_ms')->nullable();
            $table->unsignedInteger('token_count')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_logs');
    }
};
