<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Üst menünün öteki dillerdeki karşılıkları.
 *
 * Üst menü, menüler dile bağlanmadan önce tohumlanmıştı; tek satırı vardı ve o
 * satır Türkçeydi. Menüler `localeWithFallback` ile çalıştığı için /en'de üst
 * menü sessizce Türkçeye düşüyordu: sayfanın kendisi İngilizceyken gezinti
 * "Anasayfa, Hakkımızda, İletişim" yazıyordu. Alt menü aynı sorunu
 * 2026_08_29_160000 göçünde çözmüştü, üst menü geride kalmış.
 *
 * Etiketler burada duruyor, çeviri dosyasından okunmuyor: göç bir kez çalışıp
 * veriyi kuruyor, o veri artık menü modülünün — yönetici panelden değiştirdiği
 * anda buradaki metnin bir hükmü kalmıyor.
 *
 * Adresler diller arasında aynı: sayfaların İngilizce sürümü henüz yoksa
 * `pages.show` zaten varsayılan dile düşüyor ve ziyaretçi bunu sayfanın
 * üstündeki "bu içerik henüz çevrilmedi" satırından öğreniyor.
 */
return new class extends Migration
{
    private const LOCATION = 'header';

    public function up(): void
    {
        $default = (string) (DB::table('languages')->where('is_default', true)->value('code') ?? 'tr');

        $source = DB::table('menus')
            ->where('location', self::LOCATION)
            ->where('locale', $default)
            ->first();

        // Üst menü hiç kurulmamışsa (kendi tohumu kaldırılmış) burada yapılacak
        // bir şey yok: bu göç var olan menünün çevirisini açıyor, menü açmıyor.
        if ($source === null) {
            return;
        }

        $now = now();
        $labels = $this->labels();

        $sourceItems = DB::table('menu_items')
            ->where('menu_id', $source->id)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->get();

        foreach ($this->targetLocales($default) as $locale) {
            $exists = DB::table('menus')
                ->where('location', self::LOCATION)
                ->where('locale', $locale)
                ->exists();

            if ($exists) {
                continue;
            }

            $menuId = DB::table('menus')->insertGetId([
                'locale'        => $locale,
                // Kaynak menüyle aynı grup: panel çevirileri bununla eşliyor.
                'lang_group_id' => $source->lang_group_id,
                'name'          => $locale === 'en' ? 'Header Menu' : $source->name,
                'location'      => self::LOCATION,
                'is_active'     => (bool) $source->is_active,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);

            foreach ($sourceItems as $item) {
                DB::table('menu_items')->insert([
                    'locale'        => $locale,
                    'lang_group_id' => $item->lang_group_id,
                    'menu_id'       => $menuId,
                    // Üst menü tek düzey; alt öğe çıkarsa etiketi çevrilmemiş
                    // olarak kopyalanır, panelden düzeltilir.
                    'parent_id'     => null,
                    'label'         => $labels[$locale][$item->label] ?? $item->label,
                    'icon'          => $item->icon,
                    'link_type'     => $item->link_type,
                    'route_name'    => $item->route_name,
                    'route_params'  => $item->route_params,
                    'url'           => $item->url,
                    'target'        => $item->target,
                    'display_type'  => $item->display_type,
                    'sort_order'    => $item->sort_order,
                    'is_active'     => (bool) $item->is_active,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $default = (string) (DB::table('languages')->where('is_default', true)->value('code') ?? 'tr');

        $menuIds = DB::table('menus')
            ->where('location', self::LOCATION)
            ->where('locale', '!=', $default)
            ->pluck('id');

        if ($menuIds->isEmpty()) {
            return;
        }

        // Çocuklar önce: parent_id yabancı anahtarı kendi tablosunu gösteriyor.
        DB::table('menu_items')->whereIn('menu_id', $menuIds)->whereNotNull('parent_id')->delete();
        DB::table('menu_items')->whereIn('menu_id', $menuIds)->delete();
        DB::table('menus')->whereIn('id', $menuIds)->delete();
    }

    /**
     * Menü kurulacak diller — varsayılan dışındaki etkin diller.
     *
     * Göçler tohumlardan önce çalışıyor, yani taze bir kurulumda languages
     * tablosu daha boş. O durumda uygulamanın gönderdiği dil dosyaları
     * soruluyor: lang/en/site.php varsa İngilizce menü de kuruluyor.
     *
     * @return list<string>
     */
    private function targetLocales(string $default): array
    {
        /** @var list<string> $active */
        $active = DB::table('languages')->where('is_active', true)->pluck('code')->all();

        if ($active === []) {
            foreach ((array) glob(lang_path('*'), GLOB_ONLYDIR) as $directory) {
                if (is_file($directory . '/site.php')) {
                    $active[] = basename((string) $directory);
                }
            }
        }

        return array_values(array_filter(
            array_unique($active),
            static fn (string $code): bool => $code !== '' && $code !== $default,
        ));
    }

    /**
     * Varsayılan dildeki etiketin karşılığı.
     *
     * Karşılığı olmayan bir etiket olduğu gibi kopyalanıyor: menüye sonradan
     * eklenmiş bir bağlantı çevrilmemiş görünür, ama kaybolmaz.
     *
     * @return array<string, array<string, string>>
     */
    private function labels(): array
    {
        return [
            'en' => [
                'Anasayfa'   => 'Home',
                'Blog'       => 'Blog',
                'Hakkımızda' => 'About Us',
                'Galeri'     => 'Gallery',
                'İletişim'   => 'Contact',
                'SSS'        => 'FAQ',
            ],
        ];
    }
};
