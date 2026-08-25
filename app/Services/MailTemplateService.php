<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MailTemplate;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

final class MailTemplateService
{
    private const CACHE_KEY = 'mail_templates.all';
    private const CACHE_TTL = 86400; // 24 hours

    /**
     * Get all templates for admin listing.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, MailTemplate>
     */
    public function getAll(): \Illuminate\Database\Eloquent\Collection
    {
        return MailTemplate::orderBy('name')->get();
    }

    /**
     * Find a template by ID.
     */
    public function findOrFail(int $id): MailTemplate
    {
        return MailTemplate::findOrFail($id);
    }

    /**
     * Update a template.
     */
    public function update(MailTemplate $template, array $data): MailTemplate
    {
        $template->update($data);
        $this->clearCache();

        return $template;
    }

    /**
     * Reset a template to its default content.
     */
    public function resetToDefault(MailTemplate $template): MailTemplate
    {
        $defaults = $this->getDefaults();

        if (isset($defaults[$template->key])) {
            $template->update([
                'subject' => $defaults[$template->key]['subject'],
                'body'    => $defaults[$template->key]['body'],
            ]);
            $this->clearCache();
        }

        return $template;
    }

    /**
     * Clear template cache.
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Get default template contents (for reset functionality).
     *
     * @return array<string, array{subject: string, body: string}>
     */
    private function getDefaults(): array
    {
        return [
            'test' => [
                'subject' => '{site_name} — Test E-postası',
                'body'    => '<p class="em-greeting">Test E-postası</p>
<h1 class="em-heading">{mail_subject}</h1>

<p class="em-text">{mail_body}</p>

<hr class="em-divider">

<p class="em-text-sm">Bu e-posta, SMTP ayarlarınızın doğru çalışıp çalışmadığını test etmek amacıyla gönderilmiştir.</p>',
            ],
            'welcome' => [
                'subject' => 'Hoş Geldiniz - {site_name}',
                'body'    => '<p class="em-greeting">Merhaba</p>
<h1 class="em-heading">Hoş Geldiniz, {user_name}! &#127793;</h1>

<p class="em-text">
    {site_name} ailesine katıldığınız için teşekkür ederiz.
    Aramıza hoş geldiniz! Size yardımcı olmaktan mutluluk duyarız.
</p>

<hr class="em-divider">

<p class="em-heading-sm">Hesabınızla neler yapabilirsiniz?</p>

<table role="presentation" cellpadding="0" cellspacing="0" width="100%">
    <tr><td class="em-feature-td"><table role="presentation" cellpadding="0" cellspacing="0"><tr><td class="em-feature-icon-td">&#128100;</td><td class="em-feature-text-td"><strong>Profil bilgilerinizi</strong> yönetin</td></tr></table></td></tr>
    <tr><td class="em-feature-td"><table role="presentation" cellpadding="0" cellspacing="0"><tr><td class="em-feature-icon-td">&#128196;</td><td class="em-feature-text-td"><strong>İçeriklerimizi</strong> keşfedin</td></tr></table></td></tr>
    <tr><td class="em-feature-td"><table role="presentation" cellpadding="0" cellspacing="0"><tr><td class="em-feature-icon-td">&#128227;</td><td class="em-feature-text-td"><strong>Yeni yazılardan</strong> haberdar olun</td></tr></table></td></tr>
    <tr><td class="em-feature-td"><table role="presentation" cellpadding="0" cellspacing="0"><tr><td class="em-feature-icon-td">&#9993;</td><td class="em-feature-text-td"><strong>Bizimle iletişimde</strong> kalın</td></tr></table></td></tr>
</table>

<hr class="em-divider">

<p class="em-text">
    Herhangi bir sorunuz varsa bize iletişim sayfamızdan ulaşabilirsiniz.
    İyi çalışmalar dileriz!
</p>',
            ],
            'verify_email' => [
                'subject' => 'E-posta Adresinizi Doğrulayın - {site_name}',
                'body'    => '<p class="em-greeting">Merhaba</p>
<h1 class="em-heading">E-posta Adresinizi Doğrulayın</h1>

<p class="em-text">
    {user_name}, hesabınızı kullanmaya başlamak için aşağıdaki butona tıklayarak
    e-posta adresinizi doğrulayın.
</p>

<div class="em-btn-wrap">
    <a href="{verification_url}" class="em-btn">E-postamı Doğrula</a>
</div>

<hr class="em-divider">

<p class="em-text-sm">
    Bağlantının geçerlilik süresi 60 dakikadır. Bu hesabı siz oluşturmadıysanız
    bu e-postayı yok sayabilirsiniz.
</p>',
            ],
            'reset_password' => [
                'subject' => 'Şifre Sıfırlama - {site_name}',
                'body'    => '<p class="em-greeting">Güvenlik</p>
<h1 class="em-heading">Şifre Sıfırlama Talebi &#128274;</h1>

<p class="em-text">
    Merhaba, hesabınız için bir şifre sıfırlama talebi aldık.
    Şifrenizi sıfırlamak için aşağıdaki butona tıklayın:
</p>

<div class="em-btn-wrap">
    <a href="{reset_url}" class="em-btn">&#128275; Şifremi Sıfırla</a>
</div>

<table class="em-highlight" role="presentation" cellpadding="0" cellspacing="0" width="100%">
    <tr><td class="em-highlight-td"><p class="em-text-sm">&#9200; Bu şifre sıfırlama bağlantısı <strong>60 dakika</strong> içinde geçerliliğini yitirecektir.</p></td></tr>
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
            ],
            'contact_message' => [
                'subject' => 'Yeni İletişim Mesajı - {contact_subject}',
                'body'    => '<p class="em-greeting">İletişim</p>
<h1 class="em-heading">Yeni İletişim Mesajı &#128233;</h1>

<p class="em-text">Web sitesi üzerinden yeni bir iletişim mesajı alındı.</p>

<table class="em-info-box" role="presentation" cellpadding="0" cellspacing="0" width="100%">
    <tr><td class="em-info-box-td">
        <p class="em-info-row"><span class="em-info-label">Gönderen:</span> {contact_name}</p>
        <p class="em-info-row"><span class="em-info-label">E-posta:</span> {contact_email}</p>
        <p class="em-info-row"><span class="em-info-label">Telefon:</span> {contact_phone}</p>
        <p class="em-info-row"><span class="em-info-label">Konu:</span> {contact_subject}</p>
        <p class="em-info-row"><span class="em-info-label">Tarih:</span> {contact_date}</p>
    </td></tr>
</table>

<hr class="em-divider">
<p class="em-heading-sm">Mesaj İçeriği</p>
<p class="em-text">{contact_message}</p>
<hr class="em-divider">

<p class="em-text">Bu mesajı yönetim panelinden görüntüleyebilir ve yanıtlayabilirsiniz.</p>

<div class="em-btn-wrap">
    <a href="{message_url}" class="em-btn">Mesajı Görüntüle</a>
</div>',
            ],
            'contact_reply' => [
                'subject' => 'Re: {contact_subject}',
                'body'    => '<p class="em-greeting">Merhaba {contact_name},</p>
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
            ],
        ];
    }
}
