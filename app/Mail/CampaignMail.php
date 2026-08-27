<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Setting;
use App\Services\UploadService;
use App\Support\PersonName;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Symfony\Component\Mime\Email;

/**
 * One campaign, one recipient.
 *
 * The body is written in the panel's editor and stored as HTML, so it goes out
 * through the shared mail layout rather than a Blade view of its own.
 *
 * Images are embedded in the message rather than linked. A linked image is
 * blocked by default in most mail clients and breaks entirely once the mail is
 * forwarded or read offline; an embedded one is part of the message and always
 * shows.
 *
 * Every copy carries its own unsubscribe link — as a visible footer line and as
 * the List-Unsubscribe header mail clients surface as a one-click button.
 */
final class CampaignMail extends BaseMail
{
    /**
     * Local files to embed, keyed by the content id used in the HTML.
     *
     * @var array<string, string>
     */
    private array $inlineImages = [];

    public function __construct(
        public readonly Campaign $campaign,
        public readonly CampaignRecipient $recipient,
        public readonly bool $isTest = false,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->isTest
            ? '[TEST] ' . $this->campaign->subject
            : $this->campaign->subject;

        return new Envelope(
            from: new Address($this->campaign->senderAddress(), $this->campaign->senderName()),
            replyTo: $this->campaign->reply_to ? [new Address($this->campaign->reply_to)] : [],
            subject: $this->personalise($subject),
        );
    }

    public function content(): Content
    {
        $this->buildSharedData();
        $this->embedMailLogo();

        $body = $this->embedImages($this->personalise($this->campaign->body));

        // Registered after embedImages has collected them.
        if ($this->inlineImages !== []) {
            $images = $this->inlineImages;

            $this->withSymfonyMessage(function (Email $email) use ($images): void {
                foreach ($images as $cid => $path) {
                    $email->embedFromPath($path, $cid);
                }
            });
        }

        // Laravel applies the mailable's public properties *after* the data
        // passed here, so BaseMail::$emailBody (null) would overwrite it.
        // Setting the property is what actually reaches the view.
        $this->emailBody = $body;

        return new Content(
            view: 'emails.campaign',
            with: [
                'emailBody'      => $body,
                'unsubscribeUrl' => $this->unsubscribeUrl(),
                'isTest'         => $this->isTest,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return $this->campaign->attachments
            ->map(fn ($file): Attachment => Attachment::fromPath(UploadService::basePath($file->path))
                ->as($file->original_name)
                ->withMime($file->mime_type ?: 'application/octet-stream'))
            ->all();
    }

    /**
     * List-Unsubscribe lets the mail client offer its own opt-out button, which
     * keeps people from reporting the mail as spam to get rid of it.
     */
    public function headers(): Headers
    {
        $text = [];

        if (! $this->isTest && ($url = $this->unsubscribeUrl()) !== null) {
            $text['List-Unsubscribe'] = '<' . $url . '>';
            $text['List-Unsubscribe-Post'] = 'List-Unsubscribe=One-Click';
        }

        return new Headers(text: $text);
    }

    protected function emailView(): string
    {
        return 'emails.campaign';
    }

    /**
     * Replace the placeholders the editor offers, so one campaign can still
     * greet each person by name.
     */
    private function personalise(string $content): string
    {
        // Ad ve soyad ayrı tutuluyor: "Sayın {last_name}" ya da yalnızca adla
        // seslenmek tek bir isim alanıyla yapılamıyordu. {name} ikisinin
        // birleşimi olarak duruyor, eski kampanya metinleri bozulmasın.
        $firstName = (string) ($this->recipient->first_name ?? '');
        $lastName = (string) ($this->recipient->last_name ?? '');

        // Read straight from settings: envelope() runs before content(), so the
        // shared data this mail builds later is not available yet.
        $siteName = (string) Setting::getValue('site_name', config('app.name'));

        return strtr($content, [
            '{name}'       => e(PersonName::full($firstName, $lastName) ?? ''),
            '{first_name}' => e($firstName),
            '{last_name}'  => e($lastName),
            '{email}'      => e($this->recipient->email),
            '{site_name}'  => e($siteName),
        ]);
    }

    /**
     * Swap every image that lives on this site for an inline attachment.
     *
     * Images hosted elsewhere are left alone — embedding them would mean
     * fetching a third-party URL from inside the send loop, which is not
     * something a bulk mailer should do.
     */
    private function embedImages(string $html): string
    {
        return (string) preg_replace_callback(
            '#(<img\b[^>]*?\bsrc=")([^"]+)(")#i',
            function (array $matches): string {
                $path = $this->localUploadPath($matches[2]);

                if ($path === null) {
                    return $matches[0];
                }

                // Deterministic, so the same image used twice is embedded once.
                $cid = 'img-' . substr(md5($path), 0, 16);
                $this->inlineImages[$cid] = $path;

                return $matches[1] . 'cid:' . $cid . $matches[3];
            },
            $html,
        );
    }

    /**
     * Resolve a src attribute to a readable file inside the uploads directory,
     * or null when it points somewhere else.
     */
    private function localUploadPath(string $src): ?string
    {
        $src = html_entity_decode($src, ENT_QUOTES | ENT_HTML5);

        // Anything already inline, or a data URI, stays as it is.
        if (str_starts_with($src, 'cid:') || str_starts_with($src, 'data:')) {
            return null;
        }

        $appUrl = rtrim((string) config('app.url'), '/');

        if ($appUrl !== '' && str_starts_with($src, $appUrl . '/')) {
            $src = substr($src, strlen($appUrl));
        }

        if (! str_starts_with($src, '/uploads/')) {
            return null;
        }

        // Strip a cache-busting query so the path resolves on disk.
        $relative = (string) preg_replace('/[?#].*$/', '', substr($src, strlen('/uploads/')));

        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }

        $real = realpath(UploadService::basePath($relative));
        $root = realpath(UploadService::basePath());

        // Never read outside the uploads directory, whatever the editor stored.
        if ($real === false || $root === false || ! str_starts_with($real, $root)) {
            return null;
        }

        return is_file($real) && is_readable($real) ? $real : null;
    }

    private function unsubscribeUrl(): ?string
    {
        $token = $this->recipient->unsubscribe_token;

        return $token ? route('newsletter.unsubscribe', $token) : null;
    }
}
