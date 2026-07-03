<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_notifications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index(); // null = tüm admin'lere
            $table->string('type', 80)->index();    // post_failed, backup_completed, recycle_warning, health_critical, ...
            $table->string('level', 20)->default('info'); // info, success, warning, error, critical
            $table->string('title', 200);
            $table->text('message')->nullable();
            $table->string('icon', 30)->nullable();   // bi-icon name
            $table->string('action_url', 500)->nullable(); // tıklanırsa gidilecek
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notifications');
    }
};
