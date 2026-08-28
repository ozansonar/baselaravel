<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Yorum bildirimlerinin şablonları.
 *
 * Üç metin de "Mail Temaları" ekranından düzenlenebilir olmalı; sınıfların
 * içine gömülseydi metni değiştirmek için kod dağıtmak gerekirdi. Şablon
 * bulunamazsa BaseMail kendi Blade görünümüne düşüyor, yani bu satırlar
 * silinse bile mail gitmeye devam ediyor.
 */
return new class extends Migration
{
    /**
     * @return array<int, array<string, mixed>>
     */
    private function templates(): array
    {
        return [
            [
                'key'         => 'blog_comment_admin',
                'name'        => 'Yeni Yorum (Yönetici)',
                'description' => 'Bir blog yazısına yorum yapıldığında yöneticiye gönderilen bildirim.',
                'subject'     => 'Yeni Yorum: {post_title} - {site_name}',
                'body'        => '<p class="em-greeting">Yorum</p>
<h1 class="em-heading">Yeni Yorum Geldi &#128172;</h1>

<p class="em-text">
    <strong>{post_title}</strong> yazısına yeni bir yorum yapıldı. Yorum onaya
    düştü; onaylanana kadar sitede görünmüyor.
</p>

<table class="em-info-box" role="presentation" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td class="em-info-box-td">
            <p class="em-info-row"><span class="em-info-label">Yazan:</span> {comment_author}</p>
            <p class="em-info-row"><span class="em-info-label">E-posta:</span> {comment_email}</p>
            <p class="em-info-row"><span class="em-info-label">Tarih:</span> {comment_date}</p>
        </td>
    </tr>
</table>

<hr class="em-divider">

<p class="em-heading-sm">Yorum İçeriği</p>

<p class="em-text">{comment_body}</p>

<div class="em-btn-wrap">
    <a href="{comment_url}" class="em-btn">Yorumu İncele</a>
</div>',
                'variables'   => [
                    ['key' => 'comment_author', 'label' => 'Yorumu Yazan', 'example' => 'Ahmet Yılmaz'],
                    ['key' => 'comment_email', 'label' => 'Yazanın E-postası', 'example' => 'ahmet@ornek.com'],
                    ['key' => 'comment_body', 'label' => 'Yorum İçeriği', 'example' => 'Yazı için teşekkürler.'],
                    ['key' => 'comment_date', 'label' => 'Yorum Tarihi', 'example' => '29.08.2026 14:30'],
                    ['key' => 'post_title', 'label' => 'Yazı Başlığı', 'example' => 'Modern Web Teknolojileri'],
                    ['key' => 'comment_url', 'label' => 'Yorum Yönetim Bağlantısı', 'example' => 'https://example.com/admin/blog-comments/12'],
                    ['key' => 'site_name', 'label' => 'Site Adı', 'example' => 'Laravel Base'],
                ],
            ],
            [
                'key'         => 'blog_comment_received',
                'name'        => 'Yorum Alındı (Kullanıcı)',
                'description' => 'Yorum gönderildiğinde yazan kişiye giden "değerlendirme aşamasında" bildirimi.',
                'subject'     => 'Yorumunuz Alındı - {site_name}',
                'body'        => '<p class="em-greeting">Merhaba {comment_author}</p>
<h1 class="em-heading">Yorumunuz Alındı &#9989;</h1>

<p class="em-text">
    <strong>{post_title}</strong> yazısına yaptığınız yorum bize ulaştı ve
    değerlendirme aşamasında. Onaylandığında yazının altında yayınlanacak ve
    size ayrıca haber vereceğiz.
</p>

<table class="em-info-box" role="presentation" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td class="em-info-box-td">
            <p class="em-info-row"><span class="em-info-label">Yazı:</span> {post_title}</p>
            <p class="em-info-row"><span class="em-info-label">Tarih:</span> {comment_date}</p>
        </td>
    </tr>
</table>

<hr class="em-divider">

<p class="em-heading-sm">Yorumunuz</p>

<p class="em-text">{comment_body}</p>

<p class="em-text-sm">Bu yorumu siz yazmadıysanız bu e-postayı yok sayabilirsiniz.</p>',
                'variables'   => [
                    ['key' => 'comment_author', 'label' => 'Yorumu Yazan', 'example' => 'Ahmet Yılmaz'],
                    ['key' => 'comment_body', 'label' => 'Yorum İçeriği', 'example' => 'Yazı için teşekkürler.'],
                    ['key' => 'comment_date', 'label' => 'Yorum Tarihi', 'example' => '29.08.2026 14:30'],
                    ['key' => 'post_title', 'label' => 'Yazı Başlığı', 'example' => 'Modern Web Teknolojileri'],
                    ['key' => 'post_url', 'label' => 'Yazı Bağlantısı', 'example' => 'https://example.com/tr/blog/genel/yazi'],
                    ['key' => 'site_name', 'label' => 'Site Adı', 'example' => 'Laravel Base'],
                ],
            ],
            [
                'key'         => 'blog_comment_approved',
                'name'        => 'Yorum Onaylandı (Kullanıcı)',
                'description' => 'Yorum onaylandığında yazan kişiye giden yayınlandı bildirimi.',
                'subject'     => 'Yorumunuz Yayınlandı - {site_name}',
                'body'        => '<p class="em-greeting">Merhaba {comment_author}</p>
<h1 class="em-heading">Yorumunuz Yayınlandı &#127881;</h1>

<p class="em-text">
    <strong>{post_title}</strong> yazısına yaptığınız yorum onaylandı ve artık
    yazının altında herkes tarafından görülebiliyor. Katkınız için teşekkür
    ederiz.
</p>

<div class="em-btn-wrap">
    <a href="{post_url}" class="em-btn">Yorumu Sitede Gör</a>
</div>

<hr class="em-divider">

<p class="em-heading-sm">Yorumunuz</p>

<p class="em-text">{comment_body}</p>',
                'variables'   => [
                    ['key' => 'comment_author', 'label' => 'Yorumu Yazan', 'example' => 'Ahmet Yılmaz'],
                    ['key' => 'comment_body', 'label' => 'Yorum İçeriği', 'example' => 'Yazı için teşekkürler.'],
                    ['key' => 'post_title', 'label' => 'Yazı Başlığı', 'example' => 'Modern Web Teknolojileri'],
                    ['key' => 'post_url', 'label' => 'Yazı Bağlantısı', 'example' => 'https://example.com/tr/blog/genel/yazi#comments'],
                    ['key' => 'site_name', 'label' => 'Site Adı', 'example' => 'Laravel Base'],
                ],
            ],
        ];
    }

    public function up(): void
    {
        $now = now();

        foreach ($this->templates() as $template) {
            // Var olan satır ellenmiyor: yönetici metni düzenlemişse göç
            // ikinci kez çalıştığında emeğini geri almamalı.
            if (DB::table('mail_templates')->where('key', $template['key'])->exists()) {
                continue;
            }

            DB::table('mail_templates')->insert([
                'key'         => $template['key'],
                'name'        => $template['name'],
                'description' => $template['description'],
                'subject'     => $template['subject'],
                'body'        => $template['body'],
                'variables'   => json_encode($template['variables'], JSON_UNESCAPED_UNICODE),
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
                'deleted_at'  => null,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('mail_templates')
            ->whereIn('key', array_column($this->templates(), 'key'))
            ->delete();
    }
};
