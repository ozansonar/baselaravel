<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->string('url', 500);
            $table->string('url_path', 191)->index();
            $table->string('ip_address', 45)->index();
            $table->boolean('ip_masked')->default(false);
            $table->text('user_agent');
            $table->enum('device_type', ['desktop', 'mobile', 'tablet', 'bot', 'other'])->default('other');
            $table->string('browser', 50)->nullable();
            $table->string('browser_version', 20)->nullable();
            $table->string('os', 50)->nullable();
            $table->string('referrer', 500)->nullable();
            $table->string('referrer_domain', 100)->nullable()->index();
            $table->char('session_id', 40)->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_bot')->default(false)->index();
            $table->string('bot_name', 50)->nullable();
            $table->smallInteger('screen_width')->unsigned()->nullable();
            $table->smallInteger('screen_height')->unsigned()->nullable();
            $table->timestamp('viewed_at')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['viewed_at', 'is_bot']);
            $table->index(['url_path', 'viewed_at']);
            $table->index(['session_id', 'viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
