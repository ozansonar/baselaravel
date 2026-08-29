<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Alt bilginin bağlantıları menü modülüne taşınıyor.
 *
 * Bağlantılar Blade'e yazılıydı: yönetici alt bilgiye bir bağlantı eklemek
 * istediğinde koda dokunmak gerekiyordu. Menü modülü zaten "footer" konumunu
 * tanıyordu, orada bir menü yoktu — bu göç onu kuruyor.
 *
 * Sütunlar ağacın kendisinden geliyor: kök öğe sütun başlığı, çocukları o
 * sütunun bağlantıları. Konuma ikinci bir ad eklemek (footer_1, footer_2)
 * yerine böyle kuruldu; menü öğeleri ekranı zaten ana/alt öğe ilişkisini
 * yönetiyor ve sütun sayısı yöneticinin elinde kalıyor.
 *
 * Tohumlanan bağlantılar, o güne kadar alt bilgide görünenlerin birebir
 * aynısı: göçten sonra sayfa değişmiyor, yalnız yönetilebilir oluyor.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('menus')->where('location', 'footer')->exists()) {
            return;
        }

        $now = now();
        $default = (string) (DB::table('languages')->where('is_default', true)->value('code') ?? 'tr');

        $locales = $this->locales($default);

        // Menü ve her öğe, dilleri arasında aynı grup kimliğini paylaşıyor:
        // modül çevirileri bununla eşliyor.
        $menuGroup = (string) Str::uuid();
        $groups = [];

        foreach ($this->columns() as $columnIndex => $column) {
            $groups["c{$columnIndex}"] = (string) Str::uuid();

            foreach ($column['links'] as $linkIndex => $link) {
                $groups["c{$columnIndex}l{$linkIndex}"] = (string) Str::uuid();
            }
        }

        foreach ($locales as $locale) {
            $menuId = DB::table('menus')->insertGetId([
                'locale'        => $locale,
                'lang_group_id' => $menuGroup,
                'name'          => $locale === 'en' ? 'Footer Menu' : 'Alt Menü',
                'location'      => 'footer',
                'is_active'     => true,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);

            foreach ($this->columns() as $columnIndex => $column) {
                $parentId = DB::table('menu_items')->insertGetId([
                    'locale'        => $locale,
                    'lang_group_id' => $groups["c{$columnIndex}"],
                    'menu_id'       => $menuId,
                    'parent_id'     => null,
                    // Kök öğe sütun başlığı; kendisi bir yere gitmiyor.
                    'label'         => $this->label($column['label'], $locale, $default),
                    'link_type'     => 'url',
                    'url'           => '#',
                    'target'        => '_self',
                    'display_type'  => 'link',
                    'sort_order'    => $columnIndex,
                    'is_active'     => true,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);

                foreach ($column['links'] as $linkIndex => $link) {
                    DB::table('menu_items')->insert([
                        'locale'        => $locale,
                        'lang_group_id' => $groups["c{$columnIndex}l{$linkIndex}"],
                        'menu_id'       => $menuId,
                        'parent_id'     => $parentId,
                        'label'         => $this->label($link['label'], $locale, $default),
                        'link_type'     => 'route',
                        'route_name'    => $link['route'],
                        'route_params'  => isset($link['slug']) ? json_encode(['slug' => $link['slug']]) : null,
                        'target'        => '_self',
                        'display_type'  => 'link',
                        'sort_order'    => $linkIndex,
                        'is_active'     => true,
                        'created_at'    => $now,
                        'updated_at'    => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $menuIds = DB::table('menus')->where('location', 'footer')->pluck('id');

        if ($menuIds->isEmpty()) {
            return;
        }

        // Çocuklar önce: parent_id yabancı anahtarı kendi tablosunu gösteriyor.
        DB::table('menu_items')->whereIn('menu_id', $menuIds)->whereNotNull('parent_id')->delete();
        DB::table('menu_items')->whereIn('menu_id', $menuIds)->delete();
        DB::table('menus')->whereIn('id', $menuIds)->delete();
    }

    /**
     * Menü kurulacak diller.
     *
     * Göçler tohumlardan önce çalışıyor, yani taze bir kurulumda languages
     * tablosu daha boş: yalnız varsayılan dile bakılsaydı İngilizce alt bilgi
     * Türkçe etiketlerle açılırdı. Tablo boşsa uygulamanın gönderdiği dil
     * dosyaları soruluyor — lang/en/site.php varsa o dil de kuruluyor.
     *
     * @return list<string>
     */
    private function locales(string $default): array
    {
        /** @var list<string> $active */
        $active = DB::table('languages')->where('is_active', true)->pluck('code')->all();

        if ($active !== []) {
            return $active;
        }

        $shipped = [];

        foreach ((array) glob(lang_path('*'), GLOB_ONLYDIR) as $directory) {
            if (is_file($directory . '/site.php')) {
                $shipped[] = basename((string) $directory);
            }
        }

        return $shipped === [] ? [$default] : $shipped;
    }

    /**
     * Alt bilginin o güne kadar bastığı iki sütun, aynı sırayla.
     *
     * Etiketler burada duruyor, çeviri dosyasından okunmuyor: göç bir kez
     * çalışıp veriyi kuruyor, o veri artık menü modülünün. Dosyaya bağlı
     * kalsaydı göç, sonradan değişebilecek —hatta silinebilecek— anahtarlara
     * bağımlı olurdu.
     *
     * @return list<array{label: array<string, string>, links: list<array{label: array<string, string>, route: string, slug?: string}>}>
     */
    private function columns(): array
    {
        return [
            [
                'label' => ['tr' => 'Menü', 'en' => 'Menu'],
                'links' => [
                    ['label' => ['tr' => 'Anasayfa', 'en' => 'Home'],      'route' => 'home'],
                    ['label' => ['tr' => 'İçerikler', 'en' => 'Articles'], 'route' => 'blog.index'],
                    ['label' => ['tr' => 'Galeri', 'en' => 'Gallery'],     'route' => 'gallery'],
                    ['label' => ['tr' => 'SSS', 'en' => 'FAQ'],            'route' => 'faq'],
                    ['label' => ['tr' => 'İletişim', 'en' => 'Contact'],   'route' => 'contact'],
                ],
            ],
            [
                'label' => ['tr' => 'Kurumsal', 'en' => 'Corporate'],
                'links' => [
                    ['label' => ['tr' => 'Hakkımızda', 'en' => 'About Us'],                  'route' => 'pages.show', 'slug' => 'hakkimizda'],
                    ['label' => ['tr' => 'Gizlilik Politikası', 'en' => 'Privacy Policy'],   'route' => 'pages.show', 'slug' => 'gizlilik-politikasi'],
                    ['label' => ['tr' => 'Kullanım Koşulları', 'en' => 'Terms of Use'],      'route' => 'pages.show', 'slug' => 'kullanim-kosullari'],
                ],
            ],
        ];
    }

    /**
     * Etiketin bu dildeki karşılığı.
     *
     * Tanımlı olmayan bir dil varsayılan dilin etiketiyle kuruluyor; yönetici
     * menü modülünün "başka dile kopyala" akışıyla onu çevirir. Boş bırakmak
     * ya da anahtarı basmak alt bilgiyi bozardı.
     *
     * @param array<string, string> $labels
     */
    private function label(array $labels, string $locale, string $default): string
    {
        return $labels[$locale] ?? $labels[$default] ?? reset($labels);
    }
};
