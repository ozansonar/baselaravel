<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_logs', function (Blueprint $table): void {
            // ParseError olan log'ları retry edebilmek için hangi kategoriye
            // yayınlanacağını biliyor olmamız gerek (generate akışında biliniyor
            // ama log'a yazılmamış). Yeni log'lar buraya yazacak; eski log'lar null.
            $table->string('category_slug', 160)->nullable()->after('product_topic_id');
            $table->index('category_slug');
        });
    }

    public function down(): void
    {
        Schema::table('ai_logs', function (Blueprint $table): void {
            $table->dropIndex(['category_slug']);
            $table->dropColumn('category_slug');
        });
    }
};
