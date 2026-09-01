<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bir avuç ayar dile bağlanıyor.
 *
 * Ayarların çoğu dilden bağımsız (renk, telefon, anahtar, açık/kapalı) ama bir
 * kısmı ziyaretçinin okuduğu metin: alt bilgi telif satırı, mail başlığındaki
 * slogan, çalışma saatlerindeki "Kapalı". Tablo anahtar başına tek satır
 * tuttuğu için bunlar /en'de de Türkçe çıkıyordu — kodun varsayılanı çevriliydi,
 * ayar doldurulduğu anda tek dile kilitleniyordu.
 *
 * `locale` **null olabilir** ve null "bütün diller" demek. Var olan bütün
 * satırlar olduğu gibi kalıyor; bir çeviri eklendiğinde aynı anahtarın o dile
 * ait ikinci bir satırı açılıyor. Çözümleme önce isteğin dilini, bulamazsa null
 * satırı okuyor.
 *
 * Ayrı bir çeviri tablosu yerine bu biçim seçildi: ayarların %90'ı hiç
 * çevrilmeyecek ve onlar için ikinci bir tabloya JOIN atmak, her sayfada
 * ödenen bir bedel olurdu.
 *
 * @see \App\Support\TranslatableSettings
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table): void {
            $table->string('locale', 5)->nullable()->after('key');
        });

        // Anahtar tek başına benzersiz olamaz artık: aynı ayarın diller kadar
        // satırı olabilir. Dizin adı Laravel'in ürettiği ad.
        Schema::table('settings', function (Blueprint $table): void {
            $table->dropUnique('settings_key_unique');
            $table->unique(['key', 'locale']);
        });
    }

    public function down(): void
    {
        // Geri dönüşte anahtar yeniden tek başına benzersiz olacak; dile ait
        // satırlar kalırsa dizin kurulamaz. Çeviriler gidiyor, "bütün diller"
        // satırı — yani asıl değer — duruyor.
        \Illuminate\Support\Facades\DB::table('settings')->whereNotNull('locale')->delete();

        Schema::table('settings', function (Blueprint $table): void {
            $table->dropUnique(['key', 'locale']);
        });

        Schema::table('settings', function (Blueprint $table): void {
            $table->dropColumn('locale');
        });

        Schema::table('settings', function (Blueprint $table): void {
            $table->unique('key');
        });
    }
};
