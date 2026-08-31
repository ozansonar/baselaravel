<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "E-posta adresiniz değiştirildi" uyarısının şablonu.
 *
 * Mail eski adrese gidiyor; hesabı ele geçiren biri adresi değiştirdiğinde
 * gerçek sahibin durumu öğrenebileceği tek an bu. Metnin panelden
 * düzenlenebilir olması önemli: burada ne yazdığı ve nereye başvurulacağı
 * siteden siteye değişiyor.
 */
return new class extends Migration
{
    private const KEY = 'email_changed';

    public function up(): void
    {
        if (DB::table('mail_templates')->where('key', self::KEY)->exists()) {
            return;
        }

        $now = now();

        DB::table('mail_templates')->insert([
            'key'         => self::KEY,
            'name'        => 'E-posta Adresi Değişti (Güvenlik)',
            'description' => 'Hesabın e-posta adresi değiştiğinde ESKİ adrese gönderilen uyarı. Hesap ele geçirildiyse sahibinin durumu öğrenebileceği tek bildirim budur.',
            'subject'     => 'Hesabınızın e-posta adresi değiştirildi - {site_name}',
            'body'        => '<p class="em-greeting">Güvenlik</p>
<h1 class="em-heading">Hesabınızın e-posta adresi değiştirildi</h1>

<p class="em-text">
    Merhaba {user_name}, hesabınızın e-posta adresi <strong>{changed_at}</strong>
    tarihinde değiştirildi.
</p>

<table class="em-highlight" role="presentation" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td class="em-highlight-td">
            <p class="em-text-sm">Eski adres: <strong>{previous_email}</strong></p>
            <p class="em-text-sm">Yeni adres: <strong>{new_email}</strong></p>
        </td>
    </tr>
</table>

<p class="em-text">
    Bu değişikliği siz yaptıysanız yapmanız gereken bir şey yok; bu bilgilendirme
    mailini yok sayabilirsiniz.
</p>

<hr class="em-divider">

<p class="em-text">
    <strong>Bu değişikliği siz yapmadıysanız hesabınız başkasının eline geçmiş
    olabilir.</strong> Bildirimler ve şifre sıfırlama bağlantıları artık yeni
    adrese gideceği için hesabı kendi başınıza geri almanız mümkün olmayabilir.
    Vakit kaybetmeden bize ulaşın: {support_email}
</p>

<p class="em-text-sm">
    Bu mail, adresi hesabınızdan kaldırılmadan önce son kez size gönderildi.
</p>',
            'variables'   => json_encode([
                ['key' => 'user_name', 'label' => 'Kullanıcı Adı Soyadı', 'example' => 'Ahmet Yılmaz'],
                ['key' => 'site_name', 'label' => 'Site Adı', 'example' => 'Acme'],
                ['key' => 'previous_email', 'label' => 'Eski E-posta', 'example' => 'ahmet@ornek.com'],
                ['key' => 'new_email', 'label' => 'Yeni E-posta (maskeli)', 'example' => 'm***t@baska.com'],
                ['key' => 'changed_at', 'label' => 'Değişiklik Zamanı', 'example' => '31.08.2026 14:05'],
                ['key' => 'support_email', 'label' => 'Destek Adresi', 'example' => 'info@ornek.com'],
            ], JSON_UNESCAPED_UNICODE),
            'is_active'   => true,
            'created_at'  => $now,
            'updated_at'  => $now,
            'deleted_at'  => null,
        ]);
    }

    public function down(): void
    {
        DB::table('mail_templates')->where('key', self::KEY)->delete();
    }
};
