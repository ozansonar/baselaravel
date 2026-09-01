<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The shipped subject and body of every mail template, per language.
 *
 * mail_templates carries one row per (key, locale) and the admin edits those
 * rows. This file is what those rows start out as: the migration seeds from it,
 * "restore default" restores to it, and a language added later gets its rows
 * filled from it.
 *
 * Only the two recipient-facing fields live here. The name, the description and
 * the variable list are the same across languages — they label the template in
 * the panel, which is Turkish by design (App\Http\Middleware\SetAdminLocale) —
 * so the locale rows copy them from the default-language row instead of
 * repeating them once per language.
 *
 * A language with no entry here is not an error: it gets the default language's
 * content to start from, and the admin translates it in the panel.
 */
final class MailTemplateDefaults
{
    /**
     * The language every other one falls back to when it has no entry here.
     */
    public const FALLBACK = 'tr';

    /**
     * Languages this file ships content for.
     *
     * @return list<string>
     */
    public static function locales(): array
    {
        return ['tr', 'en'];
    }

    /**
     * Subject and body of every template in one language.
     *
     * @return array<string, array{subject: string, body: string}>
     */
    public static function forLocale(string $locale): array
    {
        return match ($locale) {
            'tr'    => self::turkish(),
            'en'    => self::english(),
            default => [],
        };
    }

    /**
     * The default content of one template, or null when the language does not
     * ship one.
     *
     * @return array{subject: string, body: string}|null
     */
    public static function for(string $key, string $locale): ?array
    {
        return self::forLocale($locale)[$key] ?? null;
    }

    /**
     * @return array<string, array{subject: string, body: string}>
     */
    private static function turkish(): array
    {
        return [
            'blog_comment_admin' => [
                'subject' => 'Yeni Yorum: {post_title} - {site_name}',
                'body'    => '<p class="em-greeting">Yorum</p>
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
            ],
            'blog_comment_approved' => [
                'subject' => 'Yorumunuz Yayınlandı - {site_name}',
                'body'    => '<p class="em-greeting">Merhaba {comment_author}</p>
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
            ],
            'blog_comment_received' => [
                'subject' => 'Yorumunuz Alındı - {site_name}',
                'body'    => '<p class="em-greeting">Merhaba {comment_author}</p>
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
            ],
            'contact_message' => [
                'subject' => 'Yeni İletişim Mesajı - {contact_subject}',
                'body'    => '<p class="em-greeting">İletişim</p>
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
            'email_changed' => [
                'subject' => 'Hesabınızın e-posta adresi değiştirildi - {site_name}',
                'body'    => '<p class="em-greeting">Güvenlik</p>
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
            ],
            'password_reset_code' => [
                'subject' => 'Şifre Sıfırlama Kodunuz - {site_name}',
                'body'    => '<p class="em-greeting">Güvenlik</p>
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
            ],
            'scheduled_report' => [
                'subject' => '{report_title} - {site_name}',
                'body'    => '<p class="em-greeting">Rapor</p>
<h1 class="em-heading">{report_title}</h1>

<p class="em-text">
    {frequency} çalışan raporunuz bu e-postanın ekinde. Dosya seçtiğiniz
    biçimde (Excel ya da PDF) hazırlandı.
</p>

<table class="em-info-box" role="presentation" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td class="em-info-box-td">
            <p class="em-info-row"><span class="em-info-label">Tarih aralığı:</span> {report_range}</p>
            <p class="em-info-row"><span class="em-info-label">Sıklık:</span> {frequency}</p>
        </td>
    </tr>
</table>

<p class="em-text-sm">
    Bu raporu almayı bırakmak için panelden Raporlar &rarr; Zamanlanan Raporlar
    bölümündeki tanımı kapatabilirsiniz.
</p>',
            ],
            'test' => [
                'subject' => '{site_name} — Test E-postası',
                'body'    => '<p class="em-greeting">Test E-postası</p>
<h1 class="em-heading">{mail_subject}</h1>

<p class="em-text">{mail_body}</p>

<hr class="em-divider">

<p class="em-text-sm">Bu e-posta, SMTP ayarlarınızın doğru çalışıp çalışmadığını test etmek amacıyla gönderilmiştir.</p>',
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
            ],
        ];
    }

    /**
     * @return array<string, array{subject: string, body: string}>
     */
    private static function english(): array
    {
        return [
            'blog_comment_admin' => [
                'subject' => 'New comment: {post_title} - {site_name}',
                'body'    => '<p class="em-greeting">Comment</p>
<h1 class="em-heading">A new comment arrived &#128172;</h1>

<p class="em-text">
    A new comment was left on <strong>{post_title}</strong>. It is waiting for
    approval and stays hidden on the site until then.
</p>

<table class="em-info-box" role="presentation" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td class="em-info-box-td">
            <p class="em-info-row"><span class="em-info-label">Author:</span> {comment_author}</p>
            <p class="em-info-row"><span class="em-info-label">E-mail:</span> {comment_email}</p>
            <p class="em-info-row"><span class="em-info-label">Date:</span> {comment_date}</p>
        </td>
    </tr>
</table>

<hr class="em-divider">

<p class="em-heading-sm">The comment</p>

<p class="em-text">{comment_body}</p>

<div class="em-btn-wrap">
    <a href="{comment_url}" class="em-btn">Review the comment</a>
</div>',
            ],
            'blog_comment_approved' => [
                'subject' => 'Your comment is live - {site_name}',
                'body'    => '<p class="em-greeting">Hello {comment_author}</p>
<h1 class="em-heading">Your comment is live &#127881;</h1>

<p class="em-text">
    Your comment on <strong>{post_title}</strong> has been approved and is now
    visible to everyone below the article. Thank you for contributing.
</p>

<div class="em-btn-wrap">
    <a href="{post_url}" class="em-btn">See it on the site</a>
</div>

<hr class="em-divider">

<p class="em-heading-sm">Your comment</p>

<p class="em-text">{comment_body}</p>',
            ],
            'blog_comment_received' => [
                'subject' => 'We received your comment - {site_name}',
                'body'    => '<p class="em-greeting">Hello {comment_author}</p>
<h1 class="em-heading">We received your comment &#9989;</h1>

<p class="em-text">
    Your comment on <strong>{post_title}</strong> reached us and is being
    reviewed. Once it is approved it will appear below the article, and we will
    let you know.
</p>

<table class="em-info-box" role="presentation" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td class="em-info-box-td">
            <p class="em-info-row"><span class="em-info-label">Article:</span> {post_title}</p>
            <p class="em-info-row"><span class="em-info-label">Date:</span> {comment_date}</p>
        </td>
    </tr>
</table>

<hr class="em-divider">

<p class="em-heading-sm">Your comment</p>

<p class="em-text">{comment_body}</p>

<p class="em-text-sm">If you did not write this comment you can ignore this e-mail.</p>',
            ],
            'contact_message' => [
                'subject' => 'New contact message - {contact_subject}',
                'body'    => '<p class="em-greeting">Contact</p>
<h1 class="em-heading">New contact message &#128233;</h1>

<p class="em-text">
    A new message came in through the website.
</p>

<table class="em-info-box" role="presentation" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td class="em-info-box-td">
            <p class="em-info-row"><span class="em-info-label">From:</span> {contact_name}</p>
            <p class="em-info-row"><span class="em-info-label">E-mail:</span> {contact_email}</p>
            <p class="em-info-row"><span class="em-info-label">Phone:</span> {contact_phone}</p>
            <p class="em-info-row"><span class="em-info-label">Subject:</span> {contact_subject}</p>
            <p class="em-info-row"><span class="em-info-label">Date:</span> {contact_date}</p>
        </td>
    </tr>
</table>

<hr class="em-divider">

<p class="em-heading-sm">The message</p>

<p class="em-text">{contact_message}</p>

<hr class="em-divider">

<p class="em-text">
    You can read and answer this message from the admin panel.
</p>

<div class="em-btn-wrap">
    <a href="{message_url}" class="em-btn">Open the message</a>
</div>',
            ],
            'contact_reply' => [
                'subject' => 'Re: {contact_subject}',
                'body'    => '<p class="em-greeting">Hello {contact_name},</p>
<h1 class="em-heading">A reply to your message &#9993;</h1>

<p class="em-text">Thank you for the message you sent through our contact form. Here is our reply:</p>

<table class="em-highlight" role="presentation" cellpadding="0" cellspacing="0" width="100%">
    <tr><td class="em-highlight-td"><p class="em-text">{reply_body}</p></td></tr>
</table>

<hr class="em-divider">
<p class="em-heading-sm">Your original message</p>
<p class="em-text" style="font-style: italic; opacity: 0.8;">{contact_message}</p>
<hr class="em-divider">

<p class="em-text">If you have further questions you can reply to this e-mail or reach us through our website.</p>',
            ],
            'email_changed' => [
                'subject' => 'The e-mail address on your account was changed - {site_name}',
                'body'    => '<p class="em-greeting">Security</p>
<h1 class="em-heading">The e-mail address on your account was changed</h1>

<p class="em-text">
    Hello {user_name}, the e-mail address on your account was changed on
    <strong>{changed_at}</strong>.
</p>

<table class="em-highlight" role="presentation" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td class="em-highlight-td">
            <p class="em-text-sm">Previous address: <strong>{previous_email}</strong></p>
            <p class="em-text-sm">New address: <strong>{new_email}</strong></p>
        </td>
    </tr>
</table>

<p class="em-text">
    If you made this change there is nothing to do; you can ignore this notice.
</p>

<hr class="em-divider">

<p class="em-text">
    <strong>If you did not make this change, someone else may have taken over
    your account.</strong> Notifications and password reset links now go to the
    new address, so you may not be able to recover the account on your own.
    Contact us straight away: {support_email}
</p>

<p class="em-text-sm">
    This message was sent to your old address one last time, before it was
    removed from the account.
</p>',
            ],
            'password_reset_code' => [
                'subject' => 'Your password reset code - {site_name}',
                'body'    => '<p class="em-greeting">Security</p>
<h1 class="em-heading">Your password reset code</h1>

<p class="em-text">
    Hello, we received a password reset request for your account.
    Enter the code below in the app:
</p>

<table class="em-highlight" role="presentation" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td class="em-highlight-td" align="center">
            <p class="em-heading" style="letter-spacing: 8px; margin: 0;">{code}</p>
        </td>
    </tr>
</table>

<p class="em-text-sm">
    This code expires in {expires_in} minutes.
</p>

<hr class="em-divider">

<p class="em-text">
    If you did not ask for a password reset you can ignore this e-mail. Never
    share the code with anyone; our team will never ask you for it.
</p>',
            ],
            'reset_password' => [
                'subject' => 'Password reset - {site_name}',
                'body'    => '<p class="em-greeting">Security</p>
<h1 class="em-heading">Password reset request &#128274;</h1>

<p class="em-text">
    Hello, we received a password reset request for your account.
    Click the button below to set a new password:
</p>

<div class="em-btn-wrap">
    <a href="{reset_url}" class="em-btn">&#128275; Reset my password</a>
</div>

<table class="em-highlight" role="presentation" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td class="em-highlight-td">
            <p class="em-text-sm">&#9200; This reset link expires in <strong>60 minutes</strong>.</p>
        </td>
    </tr>
</table>

<hr class="em-divider">

<p class="em-text">
    If you did not ask for a password reset you can ignore this e-mail.
    Your account is safe.
</p>

<p class="em-text-sm">
    If the button does not work, copy the link below into your browser:<br>
    <a href="{reset_url}">{reset_url}</a>
</p>',
            ],
            'scheduled_report' => [
                'subject' => '{report_title} - {site_name}',
                'body'    => '<p class="em-greeting">Report</p>
<h1 class="em-heading">{report_title}</h1>

<p class="em-text">
    Your {frequency} report is attached to this e-mail, in the format you chose
    (Excel or PDF).
</p>

<table class="em-info-box" role="presentation" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td class="em-info-box-td">
            <p class="em-info-row"><span class="em-info-label">Date range:</span> {report_range}</p>
            <p class="em-info-row"><span class="em-info-label">Frequency:</span> {frequency}</p>
        </td>
    </tr>
</table>

<p class="em-text-sm">
    To stop receiving this report, switch its definition off under
    Reports &rarr; Scheduled Reports in the admin panel.
</p>',
            ],
            'test' => [
                'subject' => '{site_name} — test e-mail',
                'body'    => '<p class="em-greeting">Test e-mail</p>
<h1 class="em-heading">{mail_subject}</h1>

<p class="em-text">{mail_body}</p>

<hr class="em-divider">

<p class="em-text-sm">This message was sent to check whether your SMTP settings work.</p>',
            ],
            'verify_email' => [
                'subject' => 'Verify your e-mail address - {site_name}',
                'body'    => '<p class="em-greeting">Hello</p>
<h1 class="em-heading">Verify your e-mail address</h1>

<p class="em-text">
    {user_name}, click the button below to verify your e-mail address and start
    using your account.
</p>

<div class="em-btn-wrap">
    <a href="{verification_url}" class="em-btn">Verify my e-mail</a>
</div>

<hr class="em-divider">

<p class="em-text-sm">
    The link is valid for 60 minutes. If you did not create this account you can
    ignore this e-mail.
</p>',
            ],
            'welcome' => [
                'subject' => 'Welcome - {site_name}',
                'body'    => '<p class="em-greeting">Hello</p>
<h1 class="em-heading">Welcome, {user_name}! &#127793;</h1>

<p class="em-text">
    Thank you for joining {site_name}.
    Welcome aboard! We are happy to have you here.
</p>

<hr class="em-divider">

<p class="em-heading-sm">What you can do with your account</p>

<table role="presentation" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td class="em-feature-td">
            <table role="presentation" cellpadding="0" cellspacing="0">
                <tr>
                    <td class="em-feature-icon-td">&#128100;</td>
                    <td class="em-feature-text-td">Manage <strong>your profile</strong></td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td class="em-feature-td">
            <table role="presentation" cellpadding="0" cellspacing="0">
                <tr>
                    <td class="em-feature-icon-td">&#128196;</td>
                    <td class="em-feature-text-td">Explore <strong>our content</strong></td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td class="em-feature-td">
            <table role="presentation" cellpadding="0" cellspacing="0">
                <tr>
                    <td class="em-feature-icon-td">&#128227;</td>
                    <td class="em-feature-text-td">Hear about <strong>new articles</strong></td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td class="em-feature-td">
            <table role="presentation" cellpadding="0" cellspacing="0">
                <tr>
                    <td class="em-feature-icon-td">&#9993;</td>
                    <td class="em-feature-text-td">Stay <strong>in touch with us</strong></td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<hr class="em-divider">

<p class="em-text">
    If you have any questions, reach us through our contact page.
    Have a good day!
</p>',
            ],
        ];
    }
}
