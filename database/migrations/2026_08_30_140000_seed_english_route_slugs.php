<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Yerleşik sayfaların İngilizce adresleri.
 *
 * Rota yolları Türkçe yazılmış: /iletisim, /galeri, /sikca-sorulan-sorular.
 * Ön ek dile göre değişiyordu ama slug değişmiyordu, yani İngilizce sayfada
 * bağlantı /en/iletisim diyordu ve /en/contact 404 veriyordu.
 *
 * Adresler özel adres tablosuna açılıyor: mekanizma zaten orada, burada
 * yalnız kutudan çıkar çıkmaz çalışması sağlanıyor. Yönetici bunları
 * panelden görebiliyor, değiştirebiliyor ya da kaldırabiliyor — Türkçe
 * adresler çalışmaya devam ettiği için kaldırmak da bir şeyi bozmuyor.
 */
return new class extends Migration
{
    /**
     * @var array<string, string> rota adı => İngilizce slug
     */
    private const SLUGS = [
        'contact' => 'contact',
        'gallery' => 'gallery',
        'faq'     => 'faq',
    ];

    public function up(): void
    {
        if (! $this->publishesEnglish()) {
            return;
        }

        $now = now();

        foreach (self::SLUGS as $route => $slug) {
            $var = DB::table('custom_routes')
                ->where('locale', 'en')
                ->where('slug', $slug)
                ->whereNull('deleted_at')
                ->exists();

            if ($var) {
                continue;
            }

            DB::table('custom_routes')->insert([
                'locale'        => 'en',
                'slug'          => $slug,
                'target_route'  => $route,
                'target_params' => null,
                'type'          => 'render',
                'is_active'     => true,
                'note'          => 'Yerleşik sayfanın İngilizce adresi',
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }
    }

    /**
     * Site İngilizce yayınlıyor mu?
     *
     * Göçler tohumlardan önce çalışıyor, yani taze bir kurulumda languages
     * tablosu daha boş. Yalnız ona bakılsaydı adresler hiç açılmaz ve
     * /en/contact ilk günden 404 verirdi. Tablo boşsa uygulamanın gönderdiği
     * dil dosyaları soruluyor — lang/en/site.php varsa site İngilizce
     * yayınlıyor demektir.
     */
    private function publishesEnglish(): bool
    {
        $kayitli = DB::table('languages')->count();

        if ($kayitli > 0) {
            return DB::table('languages')->where('code', 'en')->exists();
        }

        return is_file(lang_path('en/site.php'));
    }

    public function down(): void
    {
        DB::table('custom_routes')
            ->where('locale', 'en')
            ->whereIn('slug', array_values(self::SLUGS))
            ->where('note', 'Yerleşik sayfanın İngilizce adresi')
            ->delete();
    }
};
