<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\MailTemplate;
use App\Models\Setting;
use App\Services\UploadService;
use Illuminate\Bus\Queueable;
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
        $this->currentYear = date('Y');

        $this->prepareMailLogo();
        $this->loadThemeSettings();
    }

    /**
     * Load mail theme settings from database.
     */
    private function loadThemeSettings(): void
    {
        $getValue = fn (string $key, string $default): string =>
            Setting::where('key', $key)->value('value') ?? $default;

        try {
            $this->themePrimary = $getValue('mail_theme_primary_color', '#4f46e5');
            $this->themePrimaryDark = $getValue('mail_theme_primary_dark_color', '#4338ca');
            $this->themeBg = $getValue('mail_theme_bg_color', '#f8fafc');
            $this->themeCardBg = $getValue('mail_theme_card_bg_color', '#ffffff');
            $this->themeText = $getValue('mail_theme_text_color', '#334155');
            $this->themeMuted = $getValue('mail_theme_muted_color', '#64748b');
            $this->themeFooterText = $getValue('mail_theme_footer_text', 'Sizinle çalışmaktan mutluluk duyuyoruz.');
            $this->themeSocialLinks = $getValue('mail_theme_social_links', '1') === '1';
        } catch (\Throwable) {
            $this->themePrimary = '#4f46e5';
            $this->themePrimaryDark = '#4338ca';
            $this->themeBg = '#f8fafc';
            $this->themeCardBg = '#ffffff';
            $this->themeText = '#334155';
            $this->themeMuted = '#64748b';
            $this->themeFooterText = 'Sizinle çalışmaktan mutluluk duyuyoruz.';
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

        return MailTemplate::render($key, $variables);
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

        if ($this->mailLogoPath && !$this->logoEmbedded) {
            $this->logoEmbedded = true;
            $logoPath = $this->mailLogoPath;
            $this->withSymfonyMessage(function (\Symfony\Component\Mime\Email $email) use ($logoPath): void {
                $email->embedFromPath($logoPath, 'mail-logo');
            });
        }

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
     * Prepare mail logo: resolve physical path and set CID URL.
     */
    private function prepareMailLogo(): void
    {
        try {
            $logoRelPath = Setting::where('key', 'mail_logo')->value('value');
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
