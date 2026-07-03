<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instagram_post_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instagram_post_id')->nullable()->constrained('instagram_posts')->nullOnDelete();
            $table->string('action', 40);                 // create_container, publish, test, refresh_token
            $table->string('status', 20);                 // success, failed
            $table->json('request_payload')->nullable();
            $table->longText('response_body')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('api_duration_ms')->nullable();
            $table->timestamps();

            $table->index('action');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_post_logs');
    }
};
