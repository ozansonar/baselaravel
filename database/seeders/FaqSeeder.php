<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            [
                'question'   => 'Ürünleriniz gerçekten doğal mı?',
                'answer'     => 'Evet, tüm ürünlerimiz çiftliğimizde doğal yöntemlerle üretilmektedir. Hiçbir katkı maddesi, koruyucu veya hormon kullanılmamaktadır.',
                'sort_order' => 1,
                'is_active'  => true,
            ],
            [
                'question'   => 'Minimum sipariş tutarı ne kadar?',
                'answer'     => 'Minimum sipariş tutarımız 100 TL\'dir. 500 TL ve üzeri siparişlerde kargo ücretsizdir.',
                'sort_order' => 2,
                'is_active'  => true,
            ],
            [
                'question'   => 'Kargo süresi ne kadar?',
                'answer'     => 'Siparişiniz 1-2 iş günü içinde hazırlanıp kargoya verilir. Kargoya verildikten sonra 1-3 iş günü içinde teslimat yapılır.',
                'sort_order' => 3,
                'is_active'  => true,
            ],
            [
                'question'   => 'Ürünler nasıl paketleniyor?',
                'answer'     => 'Tüm ürünlerimiz soğuk zincir korunarak özel köpük kutularda ve buz akülerle paketlenir. Ürünlerin tazeliği teslimat anına kadar korunur.',
                'sort_order' => 4,
                'is_active'  => true,
            ],
            [
                'question'   => 'Hangi bölgelere teslimat yapıyorsunuz?',
                'answer'     => 'Türkiye\'nin her iline kargo ile teslimat yapıyoruz. Soğuk zincir taşımacılığı gerektiren ürünlerde anlaşmalı kargo firmalarımızla çalışıyoruz.',
                'sort_order' => 5,
                'is_active'  => true,
            ],
            [
                'question'   => 'Ürün iadesi yapabilir miyim?',
                'answer'     => 'Gıda ürünleri olduğundan açılmış ürünlerde iade kabul edilmemektedir. Ancak hasarlı veya hatalı ürünlerde 24 saat içinde bildirim yapmanız halinde ürününüz ücretsiz olarak yenilenir.',
                'sort_order' => 6,
                'is_active'  => true,
            ],
            [
                'question'   => 'Toptan satış yapıyor musunuz?',
                'answer'     => 'Evet, restoran, otel ve marketler için toptan satış seçeneklerimiz mevcuttur. Detaylı bilgi için bizimle iletişime geçebilirsiniz.',
                'sort_order' => 7,
                'is_active'  => true,
            ],
        ];

        foreach ($faqs as $faq) {
            Faq::updateOrCreate(
                ['question' => $faq['question']],
                $faq,
            );
        }
    }
}
