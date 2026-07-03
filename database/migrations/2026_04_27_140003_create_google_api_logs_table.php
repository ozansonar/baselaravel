<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Google API çağrı log'u — admin sağlık widget'ı + hata analizi için.
 * Retention 3 ay (PurgeOldGoogleMetrics komutu ile).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_api_logs', function (Blueprint $table) {
            $table->id();
            $table->string('service', 32);
            $table->string('operation', 64);
            $table->string('status', 16);
            $table->unsignedSmallInteger('http_code')->nullable();
            $table->text('error_message')->nullable();
            $table->json('request_payload')->nullable();
            $table->unsignedInteger('response_size')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['service', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_api_logs');
    }
};
