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
                    ['key' => 'site_name', 'label' => 'Site Adı', 'example' => 'Orhan Babanın Çiftliği'],
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
    Çiftliğimizden sofranıza en taze, en doğal ürünleri ulaştırmak için sabırsızlanıyoruz.
</p>

<hr class="em-divider">

<p class="em-heading-sm">Hesabınızla neler yapabilirsiniz?</p>

<table role="presentation" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td class="em-feature-td">
            <table role="presentation" cellpadding="0" cellspacing="0">
                <tr>
                    <td class="em-feature-icon-td">&#127793;</td>
                    <td class="em-feature-text-td"><strong>Taze ürünlere</strong> göz atın</td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td class="em-feature-td">
            <table role="presentation" cellpadding="0" cellspacing="0">
                <tr>
                    <td class="em-feature-icon-td">&#128722;</td>
                    <td class="em-feature-text-td"><strong>Kolay ve hızlı</strong> sipariş verin</td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td class="em-feature-td">
            <table role="presentation" cellpadding="0" cellspacing="0">
                <tr>
                    <td class="em-feature-icon-td">&#128230;</td>
                    <td class="em-feature-text-td"><strong>Siparişlerinizi</strong> adım adım takip edin</td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td class="em-feature-td">
            <table role="presentation" cellpadding="0" cellspacing="0">
                <tr>
                    <td class="em-feature-icon-td">&#127968;</td>
                    <td class="em-feature-text-td"><strong>Teslimat adreslerinizi</strong> kaydedin</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div class="em-btn-wrap">
    <a href="{products_url}" class="em-btn">&#127807; Ürünleri Keşfet</a>
</div>

<hr class="em-divider">

<p class="em-text">
    Herhangi bir sorunuz varsa bize iletişim sayfamızdan ulaşabilirsiniz.
    Sağlıklı ve doğal günler dileriz!
</p>',
                'variables'   => json_encode([
                    ['key' => 'user_name', 'label' => 'Kullanıcı Adı Soyadı', 'example' => 'Ahmet Yılmaz'],
                    ['key' => 'site_name', 'label' => 'Site Adı', 'example' => 'Orhan Babanın Çiftliği'],
                    ['key' => 'products_url', 'label' => 'Ürünler Sayfası URL', 'example' => 'https://orhanbabaninciftligi.com/urunler'],
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
                    ['key' => 'reset_url', 'label' => 'Şifre Sıfırlama Linki', 'example' => 'https://orhanbabaninciftligi.com/sifre-sifirla/token123'],
                    ['key' => 'site_name', 'label' => 'Site Adı', 'example' => 'Orhan Babanın Çiftliği'],
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
                    ['key' => 'contact_subject', 'label' => 'Mesaj Konusu', 'example' => 'Ürün Bilgisi'],
                    ['key' => 'contact_message', 'label' => 'Mesaj İçeriği', 'example' => 'Merhaba, ürünleriniz hakkında bilgi almak istiyorum.'],
                    ['key' => 'contact_date', 'label' => 'Mesaj Tarihi', 'example' => '12.03.2026 14:30'],
                    ['key' => 'message_url', 'label' => 'Mesaj Detay URL', 'example' => 'https://orhanbabaninciftligi.com/admin/contact-messages/1'],
                    ['key' => 'site_name', 'label' => 'Site Adı', 'example' => 'Orhan Babanın Çiftliği'],
                ]),
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
                'deleted_at'  => null,
            ],
            [
                'key'         => 'order_confirmation',
                'name'        => 'Sipariş Onayı',
                'description' => 'Müşterinin siparişi oluşturulduktan sonra gönderilir.',
                'subject'     => 'Sipariş Onayı - #{order_number}',
                'body'        => '<p class="em-greeting">Sipariş Onayı</p>
<h1 class="em-heading">Siparişiniz Alındı! &#127881;</h1>

<p class="em-text">
    Sayın {shipping_name}, siparişiniz başarıyla oluşturuldu.
    Sipariş detaylarınız aşağıda yer almaktadır.
</p>

<table class="em-info-box" role="presentation" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td class="em-info-box-td">
            <p class="em-info-row"><span class="em-info-label">Sipariş No:</span> #{order_number}</p>
            <p class="em-info-row"><span class="em-info-label">Tarih:</span> {order_date}</p>
            <p class="em-info-row"><span class="em-info-label">Durum:</span> {order_status_badge}</p>
        </td>
    </tr>
</table>

<hr class="em-divider">

<p class="em-heading-sm">Sipariş Detayları</p>

{order_items_table}

<hr class="em-divider">

<p class="em-heading-sm">Teslimat Bilgileri</p>

<table class="em-info-box" role="presentation" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td class="em-info-box-td">
            <p class="em-info-row"><span class="em-info-label">Ad Soyad:</span> {shipping_name}</p>
            <p class="em-info-row"><span class="em-info-label">Telefon:</span> {shipping_phone}</p>
            <p class="em-info-row"><span class="em-info-label">Adres:</span> {shipping_address}</p>
            <p class="em-info-row"><span class="em-info-label">İlçe / İl:</span> {shipping_district_city}</p>
            {shipping_zip_row}
        </td>
    </tr>
</table>

{order_notes_section}

<div class="em-btn-wrap">
    <a href="{order_url}" class="em-btn">&#128230; Siparişimi Görüntüle</a>
</div>

<p class="em-text">
    Siparişiniz hazırlanmaya başladığında size bilgi vereceğiz.
    Bizi tercih ettiğiniz için teşekkür ederiz!
</p>',
                'variables'   => json_encode([
                    ['key' => 'order_number', 'label' => 'Sipariş Numarası', 'example' => 'ORD-20260312-001'],
                    ['key' => 'order_date', 'label' => 'Sipariş Tarihi', 'example' => '12.03.2026 14:30'],
                    ['key' => 'order_status_badge', 'label' => 'Durum Etiketi (HTML)', 'example' => '<span class="em-badge em-badge-warning">Beklemede</span>'],
                    ['key' => 'shipping_name', 'label' => 'Teslimat Adı Soyadı', 'example' => 'Ahmet Yılmaz'],
                    ['key' => 'shipping_phone', 'label' => 'Teslimat Telefonu', 'example' => '0532 123 45 67'],
                    ['key' => 'shipping_address', 'label' => 'Teslimat Adresi', 'example' => 'Atatürk Cad. No:15'],
                    ['key' => 'shipping_district_city', 'label' => 'İlçe / İl', 'example' => 'Merkez / Bolu'],
                    ['key' => 'shipping_zip_row', 'label' => 'Posta Kodu Satırı (HTML)', 'example' => ''],
                    ['key' => 'order_notes_section', 'label' => 'Sipariş Notu Bölümü (HTML)', 'example' => ''],
                    ['key' => 'order_items_table', 'label' => 'Sipariş Ürünleri Tablosu (HTML)', 'example' => '<table class="em-table">...</table>'],
                    ['key' => 'order_url', 'label' => 'Sipariş Detay URL', 'example' => 'https://orhanbabaninciftligi.com/hesabim/siparisler/1'],
                    ['key' => 'site_name', 'label' => 'Site Adı', 'example' => 'Orhan Babanın Çiftliği'],
                ]),
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
                'deleted_at'  => null,
            ],
            [
                'key'         => 'order_status_updated',
                'name'        => 'Sipariş Durumu Güncellendi',
                'description' => 'Sipariş durumu değiştiğinde müşteriye gönderilir.',
                'subject'     => 'Sipariş Durumu Güncellendi - #{order_number}',
                'body'        => '<p class="em-greeting">Sipariş Güncelleme</p>
<h1 class="em-heading">Sipariş Durumu Güncellendi</h1>

<p class="em-text">
    Sayın {shipping_name}, #{order_number} numaralı siparişinizin durumu güncellendi.
</p>

<table class="em-info-box" role="presentation" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td class="em-info-box-td">
            <p class="em-info-row"><span class="em-info-label">Sipariş No:</span> #{order_number}</p>
            <p class="em-info-row"><span class="em-info-label">Yeni Durum:</span> {order_status_badge}</p>
            <p class="em-info-row"><span class="em-info-label">Güncelleme Tarihi:</span> {update_date}</p>
        </td>
    </tr>
</table>

<hr class="em-divider">

{status_message_section}

<hr class="em-divider">

<p class="em-heading-sm">Sipariş Özeti</p>

{order_items_table}

<div class="em-btn-wrap">
    <a href="{order_url}" class="em-btn">Sipariş Detayları</a>
</div>',
                'variables'   => json_encode([
                    ['key' => 'order_number', 'label' => 'Sipariş Numarası', 'example' => 'ORD-20260312-001'],
                    ['key' => 'shipping_name', 'label' => 'Müşteri Adı', 'example' => 'Ahmet Yılmaz'],
                    ['key' => 'order_status_badge', 'label' => 'Durum Etiketi (HTML)', 'example' => '<span class="em-badge em-badge-success">Kargoya Verildi</span>'],
                    ['key' => 'update_date', 'label' => 'Güncelleme Tarihi', 'example' => '12.03.2026 16:45'],
                    ['key' => 'status_message_section', 'label' => 'Durum Mesajı Bölümü (HTML)', 'example' => ''],
                    ['key' => 'order_items_table', 'label' => 'Sipariş Ürünleri Tablosu (HTML)', 'example' => '<table class="em-table">...</table>'],
                    ['key' => 'order_url', 'label' => 'Sipariş Detay URL', 'example' => 'https://orhanbabaninciftligi.com/hesabim/siparisler/1'],
                    ['key' => 'site_name', 'label' => 'Site Adı', 'example' => 'Orhan Babanın Çiftliği'],
                ]),
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
                'deleted_at'  => null,
            ],
        ]);
    }
};
