<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AccountDeactivatedException;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * API kimlik doğrulaması — oturumsuz.
 *
 * Ön yüzdeki {@see AuthService} oturum açıyor, oturumu yeniliyor, çıkışta
 * geçersiz kılıyor. API'de oturum yok: istek Bearer jetonuyla geliyor ve
 * sunucuda hiçbir durum tutulmuyor. Bu yüzden ayrı bir sınıf — `Auth::attempt()`
 * kullanılsaydı her API girişi bir oturum çerezi doğururdu.
 *
 * Kayıt tarafı ise AuthService'e devrediliyor: rol atama, hoş geldin maili ve
 * e-posta doğrulama bağlantısı orada duruyor ve web'den gelen kullanıcı ile
 * mobilden gelen kullanıcı arasında fark olmamalı.
 *
 * Giriş/çıkış/başarısız deneme olayları elle fırlatılıyor: denetim izi
 * {@see \App\Listeners\AuditAuthenticationEvents} bunları dinliyor ve API'den
 * yapılan girişler de kayda geçmeli.
 */
final class ApiAuthService
{
    /**
     * Olay kayıtlarında girişin nereden geldiğini gösteren guard adı.
     */
    private const GUARD = 'api';

    public function __construct(
        private readonly AuthService $authService,
    ) {}

    /**
     * Yeni hesap ve ilk jeton.
     *
     * @param array{first_name: string, last_name: string, email: string, password: string, phone?: string|null} $data
     * @return array{user: User, token: string, expires_at: string|null}
     */
    public function register(array $data, ?string $deviceName = null): array
    {
        $user = $this->authService->register($data);

        // Kayıt anında da giriş yapılmış sayılıyor: mobil uygulama kullanıcıyı
        // "şimdi bir de giriş yap" ekranına düşürmemeli. Doğrulanmamış e-posta
        // bunu engellemiyor — ön yüzde de engellemiyor, doğrulama ayrı bir adım.
        event(new Login(self::GUARD, $user, false));

        return $this->issueToken($user, $deviceName);
    }

    /**
     * Kimlik bilgilerini doğrula ve jeton üret.
     *
     * @return array{user: User, token: string, expires_at: string|null}|null
     *         Kimlik bilgileri tutmuyorsa null.
     *
     * @throws AccountDeactivatedException Şifre doğru ama hesap pasifse.
     */
    public function login(string $email, string $password, ?string $deviceName = null): ?array
    {
        $provider = $this->userProvider();
        $credentials = ['email' => $email, 'password' => $password];

        $user = $provider->retrieveByCredentials(['email' => $email]);

        if (! $user instanceof User || ! $provider->validateCredentials($user, $credentials)) {
            // Olay, denemede kullanılan adresi denetim izine yazdırıyor.
            // Dinleyici şifreyi bilerek okumuyor.
            event(new Failed(self::GUARD, $user, $credentials));

            return null;
        }

        if (! $user->is_active) {
            event(new Failed(self::GUARD, $user, $credentials));

            throw new AccountDeactivatedException();
        }

        event(new Login(self::GUARD, $user, false));

        return $this->issueToken($user, $deviceName);
    }

    /**
     * Yalnızca bu isteği yapan jetonu siler.
     *
     * Kullanıcının öteki cihazlarındaki jetonlar ayakta kalıyor: telefonundan
     * çıkış yapmak tabletindeki oturumu kapatmamalı.
     */
    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        event(new Logout(self::GUARD, $user));
    }

    /**
     * @return array{user: User, token: string, expires_at: string|null}
     */
    private function issueToken(User $user, ?string $deviceName): array
    {
        $name = $this->tokenName($deviceName);

        // Aynı cihazdan yeniden giriş eskisini geçersiz kılıyor. Yoksa her
        // açılışta bir satır daha birikir; kullanıcı "cihazlarım" listesinde
        // aynı telefonu kırk kez görür ve hiçbirini güvenle iptal edemez.
        $user->tokens()->where('name', $name)->delete();

        $expiresAt = $this->expiresAt();

        /** @var NewAccessToken $token */
        $token = $user->createToken($name, ['*'], $expiresAt);

        return [
            'user'       => $user,
            'token'      => $token->plainTextToken,
            'expires_at' => $expiresAt?->toIso8601String(),
        ];
    }

    private function tokenName(?string $deviceName): string
    {
        $name = trim((string) $deviceName);

        return $name !== '' ? $name : (string) config('api.token_name', 'api');
    }

    /**
     * Jetonun son kullanma anı — config'teki dakika sayısından.
     *
     * Satıra yazılıyor, config'ten okunmakla yetinilmiyor: istemcinin jetonun
     * ne zaman biteceğini bilmesi gerekiyor ki kullanıcıyı ortada bırakmadan
     * yeniden giriş isteyebilsin.
     */
    private function expiresAt(): ?\Illuminate\Support\Carbon
    {
        $minutes = config('sanctum.expiration');

        return $minutes === null ? null : now()->addMinutes((int) $minutes);
    }

    private function userProvider(): UserProvider
    {
        $provider = Auth::createUserProvider(
            (string) config('auth.guards.api.provider', 'users'),
        );

        if ($provider === null) {
            throw new \RuntimeException('API guard için kullanıcı sağlayıcısı tanımlı değil.');
        }

        return $provider;
    }
}
