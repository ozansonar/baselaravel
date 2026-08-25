<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Database-backed permissions.
 *
 * Roles carry permissions, users carry roles, and the policies ask the user
 * whether any of their roles grants a given permission key. The key column
 * mirrors App\Enums\PermissionKey.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('name', 150);
            $table->string('group', 50)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('deleted_at');
        });

        Schema::create('permission_role', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('permission_id')->constrained()->restrictOnDelete();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['permission_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
    }
};
