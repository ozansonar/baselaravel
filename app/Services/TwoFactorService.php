<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * İki adımlı doğrulama (TOTP, RFC 6238).
 *
 * Harici bir servise bağlanmıyor: kod, paylaşılan bir anahtardan ve saatten
 * üretiliyor. Bu bilinçli — SMS'e ya da bir sağlayıcının API'sine bağlanan
 * çözüm hem para hem de kesinti demek, ve base kit'ten türeyen her projede
 * ayrıca yapılandırma istiyor. Kullanıcının telefonundaki Google
 * Authenticator, 1Password, Authy: hepsi bu standardı konuşuyor.
 *
 * QR sunucuda üretiliyor (bacon/bacon-qr-code, saf PHP, SVG). Anahtarı bir
 * QR servisine göndermek onu üçüncü bir tarafa vermek olurdu ve o anahtar tek
 * başına ikinci adımı geçmeye yetiyor.
 *
 * Kurtarma kodları şifreli sütunda düz metin duruyor, hash'li değil:
 * kullanıcı onları bir kez görüp saklayacak ve kaybettiğinde yeniden
 * görüntüleyebilmeli. Hash'lense yalnız doğrulanabilirdi, gösterilemezdi.
 * Sütun `encrypted` cast'i ile korunuyor — veritabanı dökümü ele geçse bile
 * kodlar uygulama anahtarı olmadan okunamıyor.
 */
final class TwoFactorService
{
    /** Kod uzunluğu — kimlik doğrulayıcıların hepsi altı haneyi bekliyor. */
    private const DIGITS = 6;

    /** Kodun ömrü. Standart 30 saniye; değiştirmek uygulamaları kırar. */
    private const PERIOD = 30;

    /**
     * Kabul edilen kayma. 1 = bir önceki ve bir sonraki pencere de geçerli.
     *
     * Sıfır olsaydı telefonu birkaç saniye ileri giden herkes giremezdi;
     * büyütmek ise ele geçirilen bir kodun ömrünü uzatır.
     */
    private const WINDOW = 1;

    private const RECOVERY_CODE_COUNT = 8;

    /**
     * Yeni bir gizli anahtar üretir (base32, 32 karakter = 160 bit).
     */
    public function generateSecret(): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';

        for ($i = 0; $i < 32; $i++) {
            $secret .= $alphabet[random_int(0, 31)];
        }

        return $secret;
    }

    /**
     * Kimlik doğrulayıcıya okutulacak adres.
     *
     * Yayıncı adı panelden geliyor: kullanıcının uygulamasında "Laravel" değil
     * sitenin adı görünmeli, aynı telefonda birden çok hesap olduğunda ayırt
     * edilebilsin.
     */
    public function otpauthUri(User $user, string $secret): string
    {
        $issuer = (string) Setting::getValue('site_name', (string) config('app.name'));

        return 'otpauth://totp/'
            . rawurlencode($issuer) . ':' . rawurlencode($user->email)
            . '?secret=' . $secret
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1'
            . '&digits=' . self::DIGITS
            . '&period=' . self::PERIOD;
    }

    /**
     * QR kodunun SVG gövdesi.
     *
     * Blade tarafında doğrudan basılıyor; <img src="data:..."> yerine satır içi
     * SVG, çünkü içerik güvenlik başlıkları data: URI'leri kısıtladığında bile
     * çalışıyor ve koyu kipte renk devralıyor.
     */
    public function qrCodeSvg(User $user, string $secret, int $size = 220): string
    {
        $writer = new Writer(new ImageRenderer(
            new RendererStyle($size, 0),
            new SvgImageBackEnd(),
        ));

        $svg = $writer->writeString($this->otpauthUri($user, $secret));

        // Kütüphane XML bildirimiyle döndürüyor; sayfanın ortasına gömülecek
        // parçada bildirim geçersiz.
        return (string) preg_replace('/^<\?xml.*?\?>\s*/', '', $svg);
    }

    /**
     * Kullanıcı için kurulumu başlatır: anahtar üretilir ama açılmaz.
     *
     * Açılması ilk doğru kodun girilmesine bağlı ({@see confirm}); yoksa QR'ı
     * okutamayan kişi kendi hesabından kilitlenirdi.
     */
    public function beginSetup(User $user): string
    {
        $secret = $this->generateSecret();

        $user->forceFill([
            'two_factor_secret'       => $secret,
            'two_factor_confirmed_at' => null,
        ])->save();

        return $secret;
    }

    /**
     * Kurulumu tamamlar. Kod yanlışsa hiçbir şey değişmiyor.
     *
     * @return list<string>|null Kurtarma kodları, ya da kod yanlışsa null
     */
    public function confirm(User $user, string $code): ?array
    {
        $secret = $user->two_factor_secret;

        if ($secret === null || ! $this->verifyCode($secret, $code)) {
            return null;
        }

        $codes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at'   => now(),
        ])->save();

        return $codes;
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret'         => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at'   => null,
        ])->save();
    }

    /**
     * @return list<string>
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = $this->generateRecoveryCodes();

        $user->forceFill(['two_factor_recovery_codes' => $codes])->save();

        return $codes;
    }

    /**
     * Girişin ikinci adımı: TOTP kodu ya da kurtarma kodu.
     *
     * Kurtarma kodu kabul edilirse listeden düşüyor — tek kullanımlık olması
     * onların bütün anlamı: ele geçen bir liste ikinci kez işe yaramamalı.
     */
    public function challenge(User $user, string $code): bool
    {
        $secret = $user->two_factor_secret;

        if ($secret !== null && $this->verifyCode($secret, $code)) {
            return true;
        }

        return $this->consumeRecoveryCode($user, $code);
    }

    /**
     * Verilen kod, anahtarın şu anki (ya da komşu) penceresine uyuyor mu?
     */
    public function verifyCode(string $secret, string $code): bool
    {
        $code = preg_replace('/\D/', '', $code) ?? '';

        if (strlen($code) !== self::DIGITS) {
            return false;
        }

        $timestamp = time();

        for ($offset = -self::WINDOW; $offset <= self::WINDOW; $offset++) {
            $counter = (int) floor(($timestamp + ($offset * self::PERIOD)) / self::PERIOD);

            // hash_equals: karşılaştırma süresinden kod tahmin edilmesin.
            if (hash_equals($this->codeAt($secret, $counter), $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Yöneticiler için zorunlu mu?
     *
     * Ayar panelden açılıyor. Açıkken panele erişebilen ama 2FA kurmamış olan
     * herkes kurulum ekranına yönlendiriliyor — açık bırakılan tek kapı,
     * kapının olmaması demek.
     */
    public function requiredForAdmins(): bool
    {
        return Setting::getValue('two_factor_required_admins', '0') === '1';
    }

    /**
     * Panelde 2FA kullanan yönetici sayısı — ayar ekranı bunu gösteriyor ki
     * zorunluluğu açan kişi kaç kişiyi kurulum ekranına göndereceğini bilsin.
     */
    public function enabledAdminCount(): int
    {
        return DB::table('users')
            ->join('role_user', 'users.id', '=', 'role_user.user_id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->whereIn('roles.slug', ['admin', 'editor', 'moderator'])
            ->whereNull('users.deleted_at')
            ->whereNotNull('users.two_factor_confirmed_at')
            ->distinct()
            ->count('users.id');
    }

    /**
     * Tek bir zaman penceresinin kodu — RFC 6238'in kendisi.
     */
    private function codeAt(string $secret, int $counter): string
    {
        $binarySecret = $this->base32Decode($secret);

        if ($binarySecret === '') {
            return '';
        }

        $hash = hash_hmac('sha1', pack('N*', 0, $counter), $binarySecret, true);

        // Dinamik kesme: son baytın alt dört biti nereden okunacağını söylüyor.
        $offset = ord($hash[19]) & 0x0F;

        $value = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % (10 ** self::DIGITS);

        return str_pad((string) $value, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * @return list<string>
     */
    private function generateRecoveryCodes(): array
    {
        $codes = [];

        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            // Ortadaki tire elle yazmayı kolaylaştırıyor; kod bu hâliyle
            // saklanıyor ve bu hâliyle doğrulanıyor.
            $codes[] = Str::lower(Str::random(5)) . '-' . Str::lower(Str::random(5));
        }

        return $codes;
    }

    private function consumeRecoveryCode(User $user, string $code): bool
    {
        $codes = $user->two_factor_recovery_codes ?? [];
        $code = trim(Str::lower($code));

        $remaining = [];
        $matched = false;

        foreach ($codes as $stored) {
            if (! $matched && hash_equals((string) $stored, $code)) {
                $matched = true;

                continue;
            }

            $remaining[] = $stored;
        }

        if (! $matched) {
            return false;
        }

        $user->forceFill(['two_factor_recovery_codes' => $remaining])->save();

        return true;
    }

    private function base32Decode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = rtrim(strtoupper($secret), '=');

        $bits = '';

        foreach (str_split($secret) as $char) {
            $index = strpos($alphabet, $char);

            if ($index === false) {
                return '';
            }

            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';

        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $binary .= chr((int) bindec($byte));
            }
        }

        return $binary;
    }
}
