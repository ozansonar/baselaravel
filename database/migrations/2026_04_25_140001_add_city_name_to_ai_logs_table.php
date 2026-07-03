<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_logs', function (Blueprint $table) {
            // Otomatik blog rotasyonunda hedeflenen şehri kayda alır.
            // Null ise: şehirsiz "genel" yazı (haftada 1 senaryosu) veya
            // şehir rotasyonu kapsamı dışındaki kaynaklar (ProductFill vs.).
            $table->string('city_name', 100)->nullable()->after('product_name');
        });
    }

    public function down(): void
    {
        Schema::table('ai_logs', function (Blueprint $table) {
            $table->dropColumn('city_name');
        });
    }
};
