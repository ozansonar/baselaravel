<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\MailTemplate;
use App\Models\Setting;
use App\Services\LanguageService;
use App\Services\UploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\Factory as Queue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

abstract class BaseMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Maximum number of send attempts before giving up.
     */
    public int $tries = 3;

    /**
     * Backoff intervals (seconds) between retry attempts.
     */
    public array $backoff = [30, 60, 120];

    /**
     * Mail log ID for tracking queued mails.
     */
    public ?int $mailLogId = null;

    /**
     * Site name used across all emails.
     */
    public string $siteName;

    /**
     * Mail logo CID URL for embedding in HTML (e.g. "cid:mail-logo").
     */
    public ?string $mailLogoUrl;

    /**
     * Mail logo physical path for CID attachment.
     */
    private ?string $mailLogoPath = null;

    /**
     * Guard flag to prevent duplicate logo embedding.
     */
    private bool $logoEmbedded = false;

    /**
     * Site URL for links.
     */
    public string $siteUrl;

    /**
     * The one line under the logo in the mail header.
     *
     * It used to be the literal "Doğalın En Tazesi" — a tagline left over from
     * another project, written as HTML entities so no search for Turkish
     * letters ever found it. Every mail this kit sent carried it.
     */
    public string $siteTagline;

    /**
     * Current year for footer copyright.
     */
    public string $currentYear;

    /**
     * Mail theme colors.
     */
    public string $themePrimary;
    public string $themePrimaryDark;
    public string $themeBg;
    public string $themeCardBg;
    public string $themeText;
    public string $themeMuted;
    public string $themeFooterText;
    public bool $themeSocialLinks;

    /**
     * Rendered email body from DB template (null = use Blade view).
     */
    public ?string $emailBody = null;

    /**
     * Build shared data for all emails.
     */
    protected function buildSharedData(): void
    {
        $this->siteName = Setting::getValue('site_name', config('app.name'));
        $this->siteUrl = config('app.url', 'http://localhost');
        $this->siteTagline = (string) Setting::getValue('site_description', __('site.misc.site_description'));
        $this->currentYear = date('Y');

        $this->prepareMailLogo();
        $this->loadThemeSettings();
    }

    /**
     * Load mail theme settings from database.
     */
    private function loadThemeSettings(): void
    {
        // Setting::getValue: ayarın bu dildeki karşılığı varsa o kazanıyor.
        // Doğrudan sorgu, çevrilebilir olan mail altbilgisini her dilde aynı
        // gösterirdi. Maildeki dil zaten mühürlü (BaseMail::resolveLocale).
        $getValue = fn (string $key, string $default): string =>
            Setting::getValue($key) ?? $default;

        try {
            $this->themePrimary = $getValue('mail_theme_primary_color', '#4f46e5');
            $this->themePrimaryDark = $getValue('mail_theme_primary_dark_color', '#4338ca');
            $this->themeBg = $getValue('mail_theme_bg_color', '#f8fafc');
            $this->themeCardBg = $getValue('mail_theme_card_bg_color', '#ffffff');
            $this->themeText = $getValue('mail_theme_text_color', '#334155');
            $this->themeMuted = $getValue('mail_theme_muted_color', '#64748b');
            $this->themeFooterText = $getValue('mail_theme_footer_text', __('mail.footer_text'));
            $this->themeSocialLinks = $getValue('mail_theme_social_links', '1') === '1';
        } catch (\Throwable) {
            $this->themePrimary = '#4f46e5';
            $this->themePrimaryDark = '#4338ca';
            $this->themeBg = '#f8fafc';
            $this->themeCardBg = '#ffffff';
            $this->themeText = '#334155';
            $this->themeMuted = '#64748b';
            $this->themeFooterText = __('mail.footer_text');
            $this->themeSocialLinks = true;
        }
    }

    /**
     * Get the email view.
     * Each child must define the specific view name.
     */
    abstract protected function emailView(): string;

    /**
     * Template key for DB lookup (override in child classes).
     */
    protected function templateKey(): ?string
    {
        return null;
    }

    /**
     * Variable mapping for DB template replacement (override in child classes).
     *
     * @return array<string, string|null>
     */
    protected function templateVariables(): array
    {
        return [];
    }

    /**
     * Try to load and render a DB template. Returns rendered data or null.
     *
     * @return array{subject: string, body: string}|null
     */
    protected function resolveDbTemplate(): ?array
    {
        $key = $this->templateKey();

        if ($key === null) {
            return null;
        }

        $variables = $this->templateVariables();

        return MailTemplate::render($key, $variables, $this->resolveLocale());
    }

    /**
     * Alıcının dili.
     *
     * Varsayılan olarak maili doğuran isteğin dili: kayıt, doğrulama, şifre
     * sıfırlama ve yorum maillerini tetikleyen kişi zaten o dilde geziniyor.
     * Alıcının dilini daha iyi bilen bir kaynak varsa (yorumun bağlı olduğu
     * yazının dili, abonenin kayıtlı dili) alt sınıf bunu geçersiz kılıyor —
     * panelden tetiklenen mailler için önemli: panel Türkçeye sabit, ama
     * alıcı İngilizce yorum yapmış olabilir.
     */
    protected function resolveLocale(): string
    {
        return $this->locale ?? app()->getLocale();
    }

    /**
     * Sitenin varsayılan dili.
     *
     * Yöneticiye giden mailler bunu kullanıyor: alıcı panelin kullanıcısı,
     * paneli de tek dilde yazılmış. İsteğin dilini almak, bir ziyaretçinin
     * İngilizce sayfadan yaptığı işlemin bildirimini yöneticiye İngilizce
     * göndermek olurdu.
     */
    protected function defaultLocale(): string
    {
        return app(LanguageService::class)->defaultCode();
    }

    /**
     * Dil, gönderim anında değil kuruluş anında biliniyor.
     *
     * Kuyruğa alınan bir mail işçide çiziliyor ve orada istek dili yok;
     * Laravel de $this->locale boşsa config('app.locale')'e düşüyor, yani
     * İngilizce bir ziyaretçinin karşılama maili Türkçe gidiyordu. queue() ve
     * send() istek içinde çağrıldığı için dil burada mühürleniyor ve
     * serileştirmeyle birlikte işçiye taşınıyor.
     */
    public function queue(Queue $queue)
    {
        $this->locale ??= $this->resolveLocale();

        return parent::queue($queue);
    }

    public function later($delay, Queue $queue)
    {
        $this->locale ??= $this->resolveLocale();

        return parent::later($delay, $queue);
    }

    public function send($mailer)
    {
        $this->locale ??= $this->resolveLocale();

        return parent::send($mailer);
    }

    /**
     * Embed mail log ID as a custom header for queue tracking.
     */
    public function headers(): Headers
    {
        $headers = [];

        if ($this->mailLogId) {
            $headers['X-Mail-Log-Id'] = (string) $this->mailLogId;
        }

        return new Headers(
            text: $headers,
        );
    }

    /**
     * Default content using shared layout.
     * Embeds mail logo as CID inline image.
     * If DB template exists, uses dynamic view; otherwise falls back to Blade view.
     */
    public function content(): Content
    {
        $this->buildSharedData();

        $this->embedMailLogo();

        $dbTemplate = $this->resolveDbTemplate();

        if ($dbTemplate !== null) {
            $this->subject($dbTemplate['subject']);
            $this->emailBody = $dbTemplate['body'];

            return new Content(
                view: 'emails.dynamic',
                with: ['emailBody' => $this->emailBody],
            );
        }

        return new Content(
            view: $this->emailView(),
        );
    }

    /**
     * Attach the site logo as an inline image.
     *
     * The layout references it as cid:mail-logo, so a subclass that builds its
     * own content() has to call this or the logo arrives broken.
     */
    protected function embedMailLogo(): void
    {
        if (! $this->mailLogoPath || $this->logoEmbedded) {
            return;
        }

        $this->logoEmbedded = true;
        $logoPath = $this->mailLogoPath;

        $this->withSymfonyMessage(function (\Symfony\Component\Mime\Email $email) use ($logoPath): void {
            $email->embedFromPath($logoPath, 'mail-logo');
        });
    }

    /**
     * Prepare mail logo: resolve physical path and set CID URL.
     */
    private function prepareMailLogo(): void
    {
        try {
            // Görsel çevrilmiyor: "bütün diller" satırı.
            $logoRelPath = Setting::whereNull('locale')->where('key', 'mail_logo')->value('value');
            if ($logoRelPath) {
                $fullPath = UploadService::basePath($logoRelPath);
                if (file_exists($fullPath)) {
                    $this->mailLogoPath = $fullPath;
                    $this->mailLogoUrl = 'cid:mail-logo';

                    return;
                }
            }
        } catch (\Throwable) {
            // Ignore
        }

        $this->mailLogoPath = null;
        $this->mailLogoUrl = null;
    }
}
