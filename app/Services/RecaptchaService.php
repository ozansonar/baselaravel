<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class RecaptchaService
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    public function verify(?string $response, ?string $ip = null): bool
    {
        if (empty($response)) {
            return false;
        }

        $secret = $this->secretKey();

        if (empty($secret)) {
            Log::warning('reCAPTCHA secret key is not configured.');
            return true;
        }

        try {
            $result = Http::asForm()->post(self::VERIFY_URL, [
                'secret'   => $secret,
                'response' => $response,
                'remoteip' => $ip,
            ]);

            return $result->successful() && $result->json('success') === true;
        } catch (\Throwable $e) {
            Log::error('reCAPTCHA verification failed: ' . $e->getMessage());
            return false;
        }
    }

    public function siteKey(): string
    {
        return (string) (Setting::getValue('recaptcha_site_key')
            ?? config('services.recaptcha.site_key', ''));
    }

    public function secretKey(): string
    {
        return (string) (Setting::getValue('recaptcha_secret_key')
            ?? config('services.recaptcha.secret_key', ''));
    }

    public function isEnabled(): bool
    {
        $dbEnabled = Setting::getValue('recaptcha_enabled');

        if ($dbEnabled !== null) {
            return $dbEnabled === '1'
                && !empty($this->siteKey())
                && !empty($this->secretKey());
        }

        return !empty(config('services.recaptcha.site_key'))
            && !empty(config('services.recaptcha.secret_key'));
    }
}
