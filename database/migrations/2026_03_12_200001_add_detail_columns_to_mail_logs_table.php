<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_logs', function (Blueprint $table): void {
            $table->longText('body')->nullable()->after('subject');
            $table->string('cc', 500)->nullable()->after('to');
            $table->string('bcc', 500)->nullable()->after('cc');
            $table->string('reply_to', 255)->nullable()->after('from');
            $table->string('ip_address', 45)->nullable()->after('metadata');
            $table->foreignId('user_id')->nullable()->after('ip_address')->constrained()->nullOnDelete();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('mail_logs', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id']);
            $table->dropColumn(['body', 'cc', 'bcc', 'reply_to', 'ip_address', 'user_id']);
        });
    }
};
