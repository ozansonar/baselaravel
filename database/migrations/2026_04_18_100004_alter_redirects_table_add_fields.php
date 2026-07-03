<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('redirects', function (Blueprint $table) {
            $table->string('new_url')->nullable()->change();
            $table->boolean('is_active')->default(true)->after('last_hit_at');
            $table->string('note', 500)->nullable()->after('is_active');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('redirects', function (Blueprint $table) {
            $table->string('new_url')->nullable(false)->change();
            $table->dropColumn(['is_active', 'note']);
            $table->dropSoftDeletes();
        });
    }
};
