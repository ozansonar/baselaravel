<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\GalleryType;
use App\Models\GalleryCategory;
use App\Models\GalleryItem;
use App\Models\Slider;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Slider ve galeri için gösterim verisi.
 *
 * Ana sayfadaki slayt ile galeri ızgarası kayıt olmadan boş kalıyor; tasarımın
 * dolu haliyle görülebilmesi için birer örnek gerekiyor. Görseller de burada
 * üretiliyor — depoya ikili dosya koymamak için GD ile çiziliyorlar, marka
 * renklerinde degrade kareler.
 *
 * Varsayılan seed'e dahil DEĞİL; canlıya çıkarken çalıştırılmaz:
 *   php artisan db:seed --class=DemoMediaSeeder
 */
class DemoMediaSeeder extends Seeder
{
    /** Üretilen dosyaların ineceği yer — proje kuralı: her yükleme public/uploads. */
    private const UPLOADS = 'uploads';

    /**
     * Degrade çiftleri. Marka paletinden seçildi; her görsel farklı bir çift
     * kullanıyor ki ızgara tek renk bir duvar gibi durmasın.
     *
     * @var list<array{0: array{int, int, int}, 1: array{int, int, int}}>
     */
    private const PALETTE = [
        [[79, 70, 229],  [124, 58, 237]],
        [[124, 58, 237], [6, 182, 212]],
        [[15, 23, 42],   [79, 70, 229]],
        [[6, 182, 212],  [16, 185, 129]],
        [[219, 39, 119], [124, 58, 237]],
        [[245, 158, 11], [219, 39, 119]],
        [[37, 99, 235],  [14, 165, 233]],
        [[16, 185, 129], [59, 130, 246]],
    ];

    public function run(): void
    {
        $this->seedSliders();
        $this->seedGallery();

        // Servisler listeleri saatlik önbellekten okuyor; taze kayıtlar
        // görünmeden önce o önbellek düşmeli.
        $this->forgetCaches();
    }

    // ── Slider ──

    private function seedSliders(): void
    {
        $slides = [
            [
                'sort' => 1,
                'tr' => ['title' => 'Kurumsal altyapınız hazır', 'subtitle' => 'Blog, sayfa, galeri ve menü yönetimi kutudan çıkar çıkmaz çalışır.', 'button_text' => 'Hemen Başlayın', 'button_url' => '/tr/iletisim'],
                'en' => ['title' => 'Your corporate base is ready', 'subtitle' => 'Posts, pages, gallery and menus work the moment you open the panel.', 'button_text' => 'Get Started', 'button_url' => '/en/iletisim'],
            ],
            [
                'sort' => 2,
                'tr' => ['title' => 'Çok dilli, aramaya hazır', 'subtitle' => 'Her sayfanın kendi adresi, hreflang etiketleri ve site haritası var.', 'button_text' => 'İçerikleri Keşfet', 'button_url' => '/tr/blog'],
                'en' => ['title' => 'Multilingual and search-ready', 'subtitle' => 'Every page has its own URL, hreflang tags and a sitemap entry.', 'button_text' => 'Explore Articles', 'button_url' => '/en/blog'],
            ],
            [
                'sort' => 3,
                'tr' => ['title' => 'Panelden yönetilen görseller', 'subtitle' => 'Galeriye toplu yükleme yapın, boyutlar kendiliğinden üretilsin.', 'button_text' => 'Galeriye Git', 'button_url' => '/tr/galeri'],
                'en' => ['title' => 'Media managed from the panel', 'subtitle' => 'Upload in bulk and let the responsive sizes be generated for you.', 'button_text' => 'Open Gallery', 'button_url' => '/en/galeri'],
            ],
        ];

        foreach ($slides as $index => $slide) {
            $image = $this->makeImage('sliders', 'slider-' . $slide['sort'], 1920, 960, $index);
            $groupId = (string) Str::uuid();

            foreach (['tr', 'en'] as $locale) {
                Slider::updateOrCreate(
                    ['locale' => $locale, 'sort_order' => $slide['sort']],
                    [
                        'lang_group_id' => $groupId,
                        'title'         => $slide[$locale]['title'],
                        'subtitle'      => $slide[$locale]['subtitle'],
                        'image'         => $image,
                        'button_text'   => $slide[$locale]['button_text'],
                        'button_url'    => $slide[$locale]['button_url'],
                        'is_active'     => true,
                    ],
                );
            }
        }
    }

    // ── Galeri ──

    private function seedGallery(): void
    {
        $categories = [
            ['tr' => ['name' => 'Ofis', 'slug' => 'ofis'],      'en' => ['name' => 'Office', 'slug' => 'office'], 'sort' => 1],
            ['tr' => ['name' => 'Ekip', 'slug' => 'ekip'],      'en' => ['name' => 'Team',   'slug' => 'team'],   'sort' => 2],
            ['tr' => ['name' => 'Projeler', 'slug' => 'projeler'], 'en' => ['name' => 'Projects', 'slug' => 'projects'], 'sort' => 3],
        ];

        /** @var array<string, GalleryCategory> $trCategories */
        $trCategories = [];
        /** @var array<string, GalleryCategory> $enCategories */
        $enCategories = [];

        foreach ($categories as $category) {
            $groupId = (string) Str::uuid();

            $trCategories[$category['tr']['slug']] = GalleryCategory::updateOrCreate(
                ['locale' => 'tr', 'slug' => $category['tr']['slug']],
                ['lang_group_id' => $groupId, 'name' => $category['tr']['name'], 'sort_order' => $category['sort'], 'is_active' => true],
            );

            $enCategories[$category['tr']['slug']] = GalleryCategory::updateOrCreate(
                ['locale' => 'en', 'slug' => $category['en']['slug']],
                ['lang_group_id' => $groupId, 'name' => $category['en']['name'], 'sort_order' => $category['sort'], 'is_active' => true],
            );
        }

        $photos = [
            ['cat' => 'ofis',     'tr' => 'Çalışma alanı',     'en' => 'Workspace'],
            ['cat' => 'ofis',     'tr' => 'Toplantı odası',    'en' => 'Meeting room'],
            ['cat' => 'ekip',     'tr' => 'Tasarım ekibi',     'en' => 'Design team'],
            ['cat' => 'ekip',     'tr' => 'Geliştirme ekibi',  'en' => 'Engineering team'],
            ['cat' => 'projeler', 'tr' => 'Ürün lansmanı',     'en' => 'Product launch'],
            ['cat' => 'projeler', 'tr' => 'Saha çalışması',    'en' => 'Field work'],
            ['cat' => 'projeler', 'tr' => 'Atölye',            'en' => 'Workshop'],
            ['cat' => 'ofis',     'tr' => 'Karşılama',         'en' => 'Reception'],
        ];

        foreach ($photos as $index => $photo) {
            $image = $this->makeImage('gallery', 'galeri-' . ($index + 1), 1200, 1200, $index);
            $groupId = (string) Str::uuid();

            GalleryItem::updateOrCreate(
                ['locale' => 'tr', 'title' => $photo['tr']],
                [
                    'lang_group_id'       => $groupId,
                    'type'                => GalleryType::Photo,
                    'image'               => $image,
                    'gallery_category_id' => $trCategories[$photo['cat']]->id,
                    'sort_order'          => $index + 1,
                    'is_active'           => true,
                ],
            );

            GalleryItem::updateOrCreate(
                ['locale' => 'en', 'title' => $photo['en']],
                [
                    'lang_group_id'       => $groupId,
                    'type'                => GalleryType::Photo,
                    'image'               => $image,
                    'gallery_category_id' => $enCategories[$photo['cat']]->id,
                    'sort_order'          => $index + 1,
                    'is_active'           => true,
                ],
            );
        }
    }

    // ── Görsel üretimi ──

    /**
     * Degrade bir örnek görsel çizer ve duyarlı boyutlarıyla birlikte yazar.
     *
     * Dosya varsa yeniden üretilmiyor: seeder ikinci kez çalıştığında var olan
     * görselleri değiştirmemeli, kayıtlar zaten onlara bağlı.
     *
     * @return string Kayıtta tutulan göreli yol (ör. "gallery/galeri-1.webp")
     */
    private function makeImage(string $folder, string $name, int $width, int $height, int $paletteIndex): string
    {
        $relative = "{$folder}/{$name}.webp";
        $directory = public_path(self::UPLOADS . '/' . $folder);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $original = public_path(self::UPLOADS . '/' . $relative);

        if (!file_exists($original)) {
            $canvas = $this->drawGradient($width, $height, self::PALETTE[$paletteIndex % count(self::PALETTE)]);
            imagewebp($canvas, $original, 85);

            // Duyarlı boyutlar: upload_url($path, 'md') bunları arıyor, yoksa
            // asıl dosyaya düşüyor — ızgarada 1200px'lik kare inerdi.
            foreach (['md' => 600, 'lg' => 1200] as $size => $targetWidth) {
                if ($targetWidth >= $width) {
                    continue;
                }

                $targetHeight = (int) round($height * ($targetWidth / $width));
                $variant = imagecreatetruecolor($targetWidth, $targetHeight);
                imagecopyresampled($variant, $canvas, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
                imagewebp($variant, public_path(self::UPLOADS . "/{$folder}/{$name}-{$size}.webp"), 85);
                imagedestroy($variant);
            }

            imagedestroy($canvas);
        }

        return $relative;
    }

    /**
     * Köşegen degrade + yumuşak bir ışık lekesi.
     *
     * @param array{0: array{int, int, int}, 1: array{int, int, int}} $colors
     */
    private function drawGradient(int $width, int $height, array $colors): \GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        [$from, $to] = $colors;

        // Degrade satır satır çiziliyor; piksel piksel çizim 1920x960'ta
        // saniyeler sürüyordu, satır çözünürlüğü gözle ayırt edilmiyor.
        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / max(1, $height - 1);
            $color = imagecolorallocate(
                $image,
                (int) round($from[0] + ($to[0] - $from[0]) * $ratio),
                (int) round($from[1] + ($to[1] - $from[1]) * $ratio),
                (int) round($from[2] + ($to[2] - $from[2]) * $ratio),
            );
            imagefilledrectangle($image, 0, $y, $width, $y, $color);
        }

        // Işık lekesi: düz degrade fotoğraf yerine geçmiyor, hafif bir hacim
        // duygusu kareyi baskı hatası gibi göstermekten kurtarıyor.
        $glow = imagecolorallocatealpha($image, 255, 255, 255, 105);
        imagefilledellipse($image, (int) ($width * 0.28), (int) ($height * 0.22), (int) ($width * 0.7), (int) ($height * 0.7), $glow);

        return $image;
    }

    private function forgetCaches(): void
    {
        // Anahtarlar dil kodunu taşıyor (LocalizedCache); etkin dillerin
        // hepsi tek tek düşürülüyor, listesi sabit yazılmıyor.
        foreach (app(\App\Services\LanguageService::class)->activeCodes() as $locale) {
            Cache::forget("sliders.active.{$locale}");
            Cache::forget("gallery.photos.{$locale}");
            Cache::forget("gallery.videos.{$locale}");
        }

        Cache::forget('sitemap.urls');
    }
}
