<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mobil uygulamanın şifre sıfırlama kodu maili.
 *
 * Şablonu olmayan bir mail sınıfı panelde hiç görünmez ve sonsuza dek Blade
 * görünümüne düşer — yani yönetici metnini değiştiremez. contact_reply tam
 * olarak böyle kaybolmuştu; MailDeliveryTest artık bunu bekçiliyor.
 */
return new class extends Migration
{
    private const KEY = 'password_reset_code';

    public function up(): void
    {
        if (DB::table('mail_templates')->where('key', self::KEY)->exists()) {
            return;
        }

        $now = now();

        DB::table('mail_templates')->insert([
            'key'         => self::KEY,
            'name'        => 'Şifre Sıfırlama Kodu (Mobil)',
            'description' => 'Mobil uygulamadan şifre sıfırlama istendiğinde gönderilen altı haneli kod.',
            'subject'     => 'Şifre Sıfırlama Kodunuz - {site_name}',
            'body'        => '<p class="em-greeting">Güvenlik</p>
<h1 class="em-heading">Şifre Sıfırlama Kodunuz</h1>

<p class="em-text">
    Merhaba, hesabınız için bir şifre sıfırlama talebi aldık.
    Uygulamadaki alana aşağıdaki kodu girin:
</p>

<table class="em-highlight" role="presentation" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td class="em-highlight-td" align="center">
            <p class="em-heading" style="letter-spacing: 8px; margin: 0;">{code}</p>
        </td>
    </tr>
</table>

<p class="em-text-sm">
    Bu kod {expires_in} dakika içinde geçerliliğini yitirecektir.
</p>

<hr class="em-divider">

<p class="em-text">
    Eğer şifre sıfırlama talebinde bulunmadıysanız, bu e-postayı görmezden
    gelebilirsiniz. Kodu kimseyle paylaşmayın; ekibimiz sizden bu kodu asla
    istemez.
</p>',
            'variables'   => json_encode([
                ['key' => 'code', 'label' => 'Sıfırlama Kodu', 'example' => '482915'],
                ['key' => 'expires_in', 'label' => 'Geçerlilik Süresi (dakika)', 'example' => '60'],
                ['key' => 'site_name', 'label' => 'Site Adı', 'example' => 'Acme'],
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
