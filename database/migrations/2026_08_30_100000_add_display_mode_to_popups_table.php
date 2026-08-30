<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Duyurunun kaç kez görüneceği yöneticinin kararı olsun.
 *
 * Tek bir davranış koda gömülüydü: duyuru bir kez görülünce oturum boyunca bir
 * daha çıkmıyordu. Yanlışlıkla kapatan ziyaretçi onu bir daha göremiyordu ve
 * yöneticinin bunu değiştirme yolu yoktu.
 *
 * Varsayılan 'session': göç sonrası yürürlükteki duyurular tam olarak eskisi
 * gibi davranmaya devam ediyor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('popups', function (Blueprint $table): void {
            $table->string('display_mode', 20)->default('session')->after('size');
        });
    }

    public function down(): void
    {
        Schema::table('popups', function (Blueprint $table): void {
            $table->dropColumn('display_mode');
        });
    }
};
