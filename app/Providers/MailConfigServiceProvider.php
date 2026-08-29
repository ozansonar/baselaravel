<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mime\Address;

class MailConfigServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    /**
     * Override mail config from database settings.
     */
    public function boot(): void
    {
        if ($this->app->runningUnitTests()) {
            return;
        }

        // Ayarlar mail göndermeye kalkışılınca okunuyor, her istekte değil.
        // Önce boot içinde okunuyordu: tablo var mı diye bir şema sorgusu,
        // bir de ayar sorgusu — mail göndermeyen her sayfa (yani neredeyse
        // hepsi) iki sorgunun bedelini ödüyordu.
        //
        // Kanca mail yöneticisi kaptan çıkarken çalışıyor; yönetici ayarları
        // asıl gönderim anında okuduğu için yapılandırma zamanında yerinde.
        $this->app->resolving('mail.manager', function (): void {
            if ($this->applied) {
                return;
            }

            $this->applied = true;
            $this->applyMailConfig();
        });
    }

    /** Ayarlar istek başına bir kez uygulanıyor. */
    private bool $applied = false;

    private function applyMailConfig(): void
    {
        try {
            if (! Schema::hasTable('settings')) {
                return;
            }

            $mailSettings = Setting::where('group', 'mail')
                ->pluck('value', 'key')
                ->toArray();

            if (empty($mailSettings)) {
                return;
            }

            $mailer = $mailSettings['mail_mailer'] ?? null;
            if ($mailer) {
                Config::set('mail.default', $mailer);
            }

            $host = $mailSettings['mail_host'] ?? null;
            if ($host) {
                Config::set('mail.mailers.smtp.host', $host);
            }

            $port = $mailSettings['mail_port'] ?? null;
            if ($port) {
                Config::set('mail.mailers.smtp.port', (int) $port);
            }

            $username = $mailSettings['mail_username'] ?? null;
            if ($username) {
                Config::set('mail.mailers.smtp.username', $username);
            }

            $password = $mailSettings['mail_password'] ?? null;
            if ($password) {
                Config::set('mail.mailers.smtp.password', $password);
            }

            $encryption = $mailSettings['mail_encryption'] ?? null;
            if ($encryption !== null) {
                // tls (STARTTLS, port 587) → smtp (auto-upgrades)
                // ssl (implicit SSL, port 465) → smtps
                $scheme = match ($encryption) {
                    'tls'      => 'smtp',
                    'ssl'      => 'smtps',
                    'none', '' => 'smtp',
                    default    => $encryption,
                };
                Config::set('mail.mailers.smtp.scheme', $scheme);
            }

            $fromAddress = $mailSettings['mail_from_address'] ?? null;
            if ($fromAddress) {
                Config::set('mail.from.address', $fromAddress);
            }

            $fromName = $mailSettings['mail_from_name'] ?? null;
            if ($fromName) {
                Config::set('mail.from.name', $fromName);
            }

            // Purge cached mailer so fresh config is used
            Mail::purge('smtp');

            // Developer mode: redirect all emails to test addresses
            $mailMode = $mailSettings['mail_mode'] ?? 'normal';
            $testAddresses = $mailSettings['mail_test_addresses'] ?? '';

            if ($mailMode === 'developer' && $testAddresses) {
                $addresses = array_filter(array_map('trim', explode(',', $testAddresses)));

                if (! empty($addresses)) {
                    $recipients = array_map(
                        fn (string $addr) => new Address($addr),
                        $addresses,
                    );

                    Event::listen(MessageSending::class, function (MessageSending $event) use ($recipients): void {
                        $email = $event->message;
                        $email->to(...$recipients);
                    });
                }
            }
        } catch (\Throwable) {
            // Silently fail during migrations or when DB is unavailable
        }
    }
}
