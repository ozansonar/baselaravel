<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('name', 255);
            $table->string('description', 500)->nullable();
            $table->string('subject', 500);
            $table->longText('body');
            $table->json('variables');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });

        $this->seedTemplates();
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_templates');
    }

    private function seedTemplates(): void
    {
        $now = now();

        DB::table('mail_templates')->insert([
            [
                'key'         => 'test',
                'name'        => 'Test E-postası',
                'description' => 'SMTP ayarlarını test etmek için gönderilen e-posta.',
                'subject'     => '{site_name} — Test E-postası',
                'body'        => '<p class="em-greeting">Test E-postası</p>
<h1 class="em-heading">{mail_subject}</h1>

<p class="em-text">{mail_body}</p>

<hr class="em-divider">

<p class="em-text-sm">Bu e-posta, SMTP ayarlarınızın doğru çalışıp çalışmadığını test etmek amacıyla gönderilmiştir.</p>',
                'variables'   => json_encode([
                    ['key' => 'mail_subject', 'label' => 'E-posta Konusu', 'example' => 'Test Konusu'],
                    ['key' => 'mail_body', 'label' => 'E-posta İçeriği', 'example' => 'Bu bir test mesajıdır.'],
                    ['key' => 'site_name', 'label' => 'Site Adı', 'example' => 'Laravel Base'],
                ]),
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
                'deleted_at'  => null,
            ],
            [
                'key'         => 'welcome',
                'name'        => 'Hoş Geldiniz',
                'description' => 'Yeni üye kaydı sonrası gönderilen karşılama e-postası.',
                'subject'     => 'Hoş Geldiniz - {site_name}',
                'body'        => '<p class="em-greeting">Merhaba</p>
<h1 class="em-heading">Hoş Geldiniz, {user_name}! &#127793;</h1>

<p class="em-text">
    {site_name} ailesine katıldığınız için teşekkür ederiz.
    Aramıza hoş geldiniz! Size yardımcı olmaktan mutluluk duyarız.
</p>

<hr class="em-divider">

<p class="em-heading-sm">Hesabınızla neler yapabilirsiniz?</p>

<table role="presentation" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td class="em-feature-td">
            <table role="presentation" cellpadding="0" cellspacing="0">
                <tr>
                    <td class="em-feature-icon-td">&#128100;</td>
                    <td class="em-feature-text-td"><strong>Profil bilgilerinizi</strong> yönetin</td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td class="em-feature-td">
            <table role="presentation" cellpadding="0" cellspacing="0">
                <tr>
                    <td class="em-feature-icon-td">&#128196;</td>
                    <td class="em-feature-text-td"><strong>İçeriklerimizi</strong> keşfedin</td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td class="em-feature-td">
            <table role="presentation" cellpadding="0" cellspacing="0">
                <tr>
                    <td class="em-feature-icon-td">&#128227;</td>
                    <td class="em-feature-text-td"><strong>Yeni yazılardan</strong> haberdar olun</td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td class="em-feature-td">
            <table role="presentation" cellpadding="0" cellspacing="0">
                <tr>
                    <td class="em-feature-icon-td">&#9993;</td>
                    <td class="em-feature-text-td"><strong>Bizimle iletişimde</strong> kalın</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<hr class="em-divider">

<p class="em-text">
    Herhangi bir sorunuz varsa bize iletişim sayfamızdan ulaşabilirsiniz.
    İyi çalışmalar dileriz!
</p>',
                'variables'   => json_encode([
                    ['key' => 'user_name', 'label' => 'Kullanıcı Adı Soyadı', 'example' => 'Ahmet Yılmaz'],
                    ['key' => 'site_name', 'label' => 'Site Adı', 'example' => 'Laravel Base'],
                ]),
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
                'deleted_at'  => null,
            ],
            [
                'key'         => 'reset_password',
                'name'        => 'Şifre Sıfırlama',
                'description' => 'Kullanıcı şifre sıfırlama talebinde bulunduğunda gönderilir.',
                'subject'     => 'Şifre Sıfırlama - {site_name}',
                'body'        => '<p class="em-greeting">Güvenlik</p>
<h1 class="em-heading">Şifre Sıfırlama Talebi &#128274;</h1>

<p class="em-text">
    Merhaba, hesabınız için bir şifre sıfırlama talebi aldık.
    Şifrenizi sıfırlamak için aşağıdaki butona tıklayın:
</p>

<div class="em-btn-wrap">
    <a href="{reset_url}" class="em-btn">&#128275; Şifremi Sıfırla</a>
</div>

<table class="em-highlight" role="presentation" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td class="em-highlight-td">
            <p class="em-text-sm">&#9200; Bu şifre sıfırlama bağlantısı <strong>60 dakika</strong> içinde geçerliliğini yitirecektir.</p>
        </td>
    </tr>
</table>

<hr class="em-divider">

<p class="em-text">
    Eğer şifre sıfırlama talebinde bulunmadıysanız, bu e-postayı görmezden gelebilirsiniz.
    Hesabınız güvende.
</p>

<p class="em-text-sm">
    Butona tıklayamıyorsanız aşağıdaki bağlantıyı tarayıcınıza kopyalayıp yapıştırın:<br>
    <a href="{reset_url}">{reset_url}</a>
</p>',
                'variables'   => json_encode([
                    ['key' => 'reset_url', 'label' => 'Şifre Sıfırlama Linki', 'example' => 'https://example.com/sifre-sifirla/token123'],
                    ['key' => 'site_name', 'label' => 'Site Adı', 'example' => 'Laravel Base'],
                ]),
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
                'deleted_at'  => null,
            ],
            [
                'key'         => 'contact_message',
                'name'        => 'İletişim Mesajı Bildirimi',
                'description' => 'Yeni iletişim formu mesajı geldiğinde admin\'e gönderilir.',
                'subject'     => 'Yeni İletişim Mesajı - {contact_subject}',
                'body'        => '<p class="em-greeting">İletişim</p>
<h1 class="em-heading">Yeni İletişim Mesajı &#128233;</h1>

<p class="em-text">
    Web sitesi üzerinden yeni bir iletişim mesajı alındı.
</p>

<table class="em-info-box" role="presentation" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td class="em-info-box-td">
            <p class="em-info-row"><span class="em-info-label">Gönderen:</span> {contact_name}</p>
            <p class="em-info-row"><span class="em-info-label">E-posta:</span> {contact_email}</p>
            <p class="em-info-row"><span class="em-info-label">Telefon:</span> {contact_phone}</p>
            <p class="em-info-row"><span class="em-info-label">Konu:</span> {contact_subject}</p>
            <p class="em-info-row"><span class="em-info-label">Tarih:</span> {contact_date}</p>
        </td>
    </tr>
</table>

<hr class="em-divider">

<p class="em-heading-sm">Mesaj İçeriği</p>

<p class="em-text">{contact_message}</p>

<hr class="em-divider">

<p class="em-text">
    Bu mesajı yönetim panelinden görüntüleyebilir ve yanıtlayabilirsiniz.
</p>

<div class="em-btn-wrap">
    <a href="{message_url}" class="em-btn">Mesajı Görüntüle</a>
</div>',
                'variables'   => json_encode([
                    ['key' => 'contact_name', 'label' => 'Gönderen Adı', 'example' => 'Mehmet Demir'],
                    ['key' => 'contact_email', 'label' => 'Gönderen E-posta', 'example' => 'mehmet@example.com'],
                    ['key' => 'contact_phone', 'label' => 'Gönderen Telefon', 'example' => '0532 123 45 67'],
                    ['key' => 'contact_subject', 'label' => 'Mesaj Konusu', 'example' => 'Bilgi Talebi'],
                    ['key' => 'contact_message', 'label' => 'Mesaj İçeriği', 'example' => 'Merhaba, hizmetleriniz hakkında bilgi almak istiyorum.'],
                    ['key' => 'contact_date', 'label' => 'Mesaj Tarihi', 'example' => '12.03.2026 14:30'],
                    ['key' => 'message_url', 'label' => 'Mesaj Detay URL', 'example' => 'https://example.com/admin/contact-messages/1'],
                    ['key' => 'site_name', 'label' => 'Site Adı', 'example' => 'Laravel Base'],
                ]),
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
                'deleted_at'  => null,
            ],
        ]);
    }
};
