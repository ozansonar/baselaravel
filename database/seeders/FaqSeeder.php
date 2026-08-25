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
                'question'   => 'Bu platform nedir?',
                'answer'     => 'Bu, Laravel Base kiti ile oluşturulmuş örnek bir kurumsal web sitesidir. Bu içerikleri admin panelinden düzenleyerek kendi kurumunuza uyarlayabilirsiniz.',
                'sort_order' => 1,
                'is_active'  => true,
            ],
            [
                'question'   => 'Nasıl üye olabilirim?',
                'answer'     => 'Sağ üstteki "Üye Ol" butonuna tıklayarak birkaç adımda ücretsiz hesap oluşturabilirsiniz. Kayıt sonrası profilinizi hesap sayfanızdan yönetebilirsiniz.',
                'sort_order' => 2,
                'is_active'  => true,
            ],
            [
                'question'   => 'Şifremi unuttum, ne yapmalıyım?',
                'answer'     => 'Giriş sayfasındaki "Şifremi unuttum" bağlantısını kullanarak e-posta adresinize sıfırlama bağlantısı gönderebilirsiniz.',
                'sort_order' => 3,
                'is_active'  => true,
            ],
            [
                'question'   => 'İçerikleri nereden takip edebilirim?',
                'answer'     => 'Blog bölümünden en güncel yazılara, duyurulara ve rehberlere ulaşabilirsiniz. Kategorilere göre filtreleme yapabilirsiniz.',
                'sort_order' => 4,
                'is_active'  => true,
            ],
            [
                'question'   => 'Sizinle nasıl iletişime geçebilirim?',
                'answer'     => 'İletişim sayfasındaki formu doldurarak veya orada yer alan e-posta ve telefon bilgilerini kullanarak bize kolayca ulaşabilirsiniz.',
                'sort_order' => 5,
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
