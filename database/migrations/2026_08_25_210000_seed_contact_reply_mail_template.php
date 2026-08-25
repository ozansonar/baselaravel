<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the contact reply template.
 *
 * ContactMessageReplyMail has always declared templateKey() = 'contact_reply'
 * and MailTemplateService has always known a default for it, but no row was
 * ever seeded — so the mail templates screen never listed it. Replying to a
 * contact message silently fell back to the Blade view, and the one mail an
 * admin most wants to word themselves was the one they could not edit.
 */
return new class extends Migration
{
    private const KEY = 'contact_reply';

    public function up(): void
    {
        if (DB::table('mail_templates')->where('key', self::KEY)->exists()) {
            return;
        }

        $now = now();

        DB::table('mail_templates')->insert([
            'key'         => self::KEY,
            'name'        => 'İletişim Mesajı Yanıtı',
            'description' => 'İletişim formundan gelen bir mesaja panelden yanıt verildiğinde gönderilir.',
            'subject'     => 'Re: {contact_subject}',
            'body'        => '<p class="em-greeting">Merhaba {contact_name},</p>
<h1 class="em-heading">Mesajınıza Yanıt &#9993;</h1>

<p class="em-text">İletişim formundan gönderdiğiniz mesajınız için teşekkür ederiz. Yanıtımız aşağıdadır:</p>

<table class="em-highlight" role="presentation" cellpadding="0" cellspacing="0" width="100%">
    <tr><td class="em-highlight-td"><p class="em-text">{reply_body}</p></td></tr>
</table>

<hr class="em-divider">
<p class="em-heading-sm">Orijinal Mesajınız</p>
<p class="em-text" style="font-style: italic; opacity: 0.8;">{contact_message}</p>
<hr class="em-divider">

<p class="em-text">Başka sorularınız varsa bu e-postayı yanıtlayabilir veya web sitemiz üzerinden bize ulaşabilirsiniz.</p>',
            'variables'   => json_encode([
                ['key' => 'contact_name',    'label' => 'Gönderen Adı',      'example' => 'Ahmet Yılmaz'],
                ['key' => 'contact_subject', 'label' => 'Orijinal Konu',     'example' => 'Fiyat teklifi'],
                ['key' => 'contact_message', 'label' => 'Orijinal Mesaj',    'example' => 'Merhaba, bilgi almak istiyorum.'],
                ['key' => 'reply_body',      'label' => 'Yanıt Metni',       'example' => 'Talebiniz için teşekkürler...'],
                ['key' => 'site_name',       'label' => 'Site Adı',          'example' => 'Acme'],
                ['key' => 'site_url',        'label' => 'Site Adresi',       'example' => 'https://example.com'],
            ], JSON_UNESCAPED_UNICODE),
            'is_active'   => true,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('mail_templates')->where('key', self::KEY)->delete();
    }
};
