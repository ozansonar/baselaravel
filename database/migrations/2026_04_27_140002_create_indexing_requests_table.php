<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Google Indexing API gönderim kuyruğu ve geçmişi.
 * Yeni içerik (Product/BlogPost/CityLandingPage/Page) yayınlandığında
 * Observer bir satır açar, queue worker arka planda Google'a gönderir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indexing_requests', function (Blueprint $table) {
            $table->id();
            $table->string('url', 1000);
            $table->string('type', 32)->default('URL_UPDATED');
            $table->string('status', 16)->default('pending');
            $table->string('related_type', 191)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->json('response')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['related_type', 'related_id']);
            $table->index('status');
            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indexing_requests');
    }
};
