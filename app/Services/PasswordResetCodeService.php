<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\PasswordResetCodeMail;
use App\Models\User;
use Illuminate\Auth\Passwords\DatabaseTokenRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

/**
 * Mobil uygulamadan şifre sıfırlama — bağlantı yerine altı haneli kod.
 *
 * Web'de sıfırlama bağlantısı mail'den tarayıcıya gidiyor ve şifre orada
 * değiştiriliyor. Mobilde bu akış kopuk: kullanıcı uygulamadan çıkıp tarayıcıda
 * işini bitirip geri dönmek zorunda kalıyor. Kod ile kopukluk yok — altı haneyi
 * uygulamaya yazıyor, yeni şifresini de orada belirliyor.
 *
 * Kod, bağlantının taşıdığı jetonun yerine geçiyor: aynı `password_reset_tokens`
 * tablosuna, aynı biçimde (hash'lenmiş) yazılıyor ve doğrulaması Laravel'in
 * kendi broker'ından geçiyor. Yani iki akış aynı geçerlilik süresini ve aynı
 * "bir kullanıcı için tek aktif istek" kuralını paylaşıyor; kod istenirse
 * web'den alınmış bağlantı, bağlantı istenirse kod geçersiz oluyor.
 *
 * ── Altı hane neden yeterli ──
 *
 * Tek başına değil. Bir milyon olasılık, sınırsız denemeye açık bırakılsaydı
 * dakikalar içinde tükenirdi. Üç şey bir arada tutuyor:
 *
 *  - kod 60 dakika sonra ölüyor (config/auth.php'deki `expire`),
 *  - sıfırlama ucu e-posta+IP başına dakikada birkaç denemeye sınırlı
 *    (`throttle:api-password`), yani bir saatte birkaç yüz deneme yapılabiliyor,
 *  - başarılı sıfırlama satırı hemen siliyor.
 *
 * Hız sınırı bu tasarımın süsü değil taşıyıcı direği: kaldırılırsa kod kırılır.
 */
final class PasswordResetCodeService
{
    private const CODE_LENGTH = 6;

    public function __construct(
        private readonly AuthService $authService,
        private readonly MailService $mailService,
        private readonly SessionRevoker $sessionRevoker,
    ) {}

    /**
     * Kod üret, sakla ve gönder.
     *
     * Adres kayıtlı değilse hiçbir şey yapılmıyor ama çağıran bunu öğrenmiyor:
     * "böyle bir hesap yok" cevabı, kayıtlı adresleri tek tek denemeye açık
     * kapı bırakır. Uç her durumda aynı yanıtı veriyor.
     */
    public function sendCode(string $email): void
    {
        $user = User::where('email', $email)->first();

        if (! $user instanceof User || ! $user->is_active) {
            return;
        }

        // Aynı dakika içinde arka arkaya kod istenmesini çerçevenin kendi
        // kuralı engelliyor (config/auth.php'deki `throttle`); hız sınırının
        // yanında ikinci bir fren.
        if ($this->tokens()->recentlyCreatedToken($user)) {
            return;
        }

        $code = $this->generateCode();

        DB::table($this->table())->updateOrInsert(
            ['email' => $user->email],
            // Broker doğrularken Hash::check kullanıyor; düz metin saklanmıyor
            // ki veritabanını gören biri hesapları ele geçiremesin.
            ['token' => Hash::make($code), 'created_at' => now()],
        );

        try {
            $this->mailService->queue(
                $user->email,
                new PasswordResetCodeMail($code, $this->expiresInMinutes()),
            );
        } catch (\Throwable $e) {
            Log::warning('Şifre sıfırlama kodu kuyruğa eklenemedi', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }

        AuditLogger::custom('Şifre sıfırlama kodu istendi', ['e-posta' => $user->email]);
    }

    /**
     * Kodu doğrula ve şifreyi değiştir.
     *
     * Doğrulama ve kaydetme {@see AuthService::resetPassword()} üzerinden —
     * web ile aynı yol. Denetim kaydı ve `PasswordReset` olayı da oradan
     * çıkıyor; ayrı yazılsaydı mobilden yapılan sıfırlama denetim izinde
     * görünmezdi.
     */
    public function reset(string $email, string $code, string $password): bool
    {
        $status = $this->authService->resetPassword([
            'email'                 => $email,
            'password'              => $password,
            'password_confirmation' => $password,
            'token'                 => $code,
        ]);

        if ($status !== Password::PASSWORD_RESET) {
            return false;
        }

        // Şifresini sıfırlayan kişi çoğu zaman hesabının elden çıktığını
        // düşündüğü için buradadır. Eski oturumlar ve eski jetonlar ayakta
        // kalırsa sıfırlama, erişimi geri almak yerine yalnızca bir parola
        // değişikliği olur.
        $user = User::where('email', $email)->first();

        if ($user instanceof User) {
            $this->sessionRevoker->revoke($user);
        }

        return true;
    }

    public function expiresInMinutes(): int
    {
        return (int) config('auth.passwords.users.expire', 60);
    }

    /**
     * Baştaki sıfırlar korunuyor: "042915" altı hanedir, 42915 değil.
     */
    private function generateCode(): string
    {
        return str_pad(
            (string) random_int(0, (10 ** self::CODE_LENGTH) - 1),
            self::CODE_LENGTH,
            '0',
            STR_PAD_LEFT,
        );
    }

    private function table(): string
    {
        return (string) config('auth.passwords.users.table', 'password_reset_tokens');
    }

    private function tokens(): DatabaseTokenRepository
    {
        /** @var DatabaseTokenRepository $repository */
        $repository = Password::broker()->getRepository();

        return $repository;
    }
}
