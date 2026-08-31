<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TokenAbility;
use App\Exceptions\AccountDeactivatedException;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Database\Eloquent\Collection;
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
    public function register(array $data, ?string $deviceName = null, array $abilities = []): array
    {
        $user = $this->authService->register($data);

        // Kayıt anında da giriş yapılmış sayılıyor: mobil uygulama kullanıcıyı
        // "şimdi bir de giriş yap" ekranına düşürmemeli. Doğrulanmamış e-posta
        // bunu engellemiyor — ön yüzde de engellemiyor, doğrulama ayrı bir adım.
        event(new Login(self::GUARD, $user, false));

        return $this->issueToken($user, $deviceName, $abilities);
    }

    /**
     * Kimlik bilgilerini doğrula ve jeton üret.
     *
     * @return array{user: User, token: string, expires_at: string|null}|null
     *         Kimlik bilgileri tutmuyorsa null.
     *
     * @throws AccountDeactivatedException Şifre doğru ama hesap pasifse.
     */
    public function login(string $email, string $password, ?string $deviceName = null, array $abilities = []): ?array
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

        return $this->issueToken($user, $deviceName, $abilities);
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
     * Kullanıcının açık oturumları — en son kullanılan başta.
     *
     * Süresi dolmuş jetonlar listeye girmiyor: Sanctum onları zaten kabul
     * etmiyor, listede görünmeleri kullanıcıya kapatabileceği bir oturum varmış
     * gibi gösterirdi. Temizlikleri haftalık göreve bırakılmış.
     *
     * @return Collection<int, PersonalAccessToken>
     */
    public function devices(User $user): Collection
    {
        /** @var Collection<int, PersonalAccessToken> $tokens */
        $tokens = $user->tokens()
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->get();

        return $tokens;
    }

    /**
     * Tek bir cihazın oturumunu kapatır.
     *
     * Sorgu kullanıcıya bağlı: başkasının jeton kimliğini yazan biri "yetkin
     * yok" değil "yok" cevabı alıyor. Ayrımı söylemek, kimlik numaralarını tek
     * tek deneyerek başka hesapların oturum sayısını öğrenmeye yarardı.
     */
    public function revokeDevice(User $user, int $tokenId): bool
    {
        return $user->tokens()->whereKey($tokenId)->delete() > 0;
    }

    /**
     * Bu cihaz hariç bütün oturumları kapatır.
     *
     * Mevcut jeton bilerek korunuyor: "diğer cihazlardan çık" diyen kullanıcı
     * kendi uygulamasından da atılmayı beklemiyor. Kendi oturumunu kapatmak
     * istiyorsa zaten çıkış var.
     *
     * @return int Kapatılan oturum sayısı
     */
    public function revokeOtherDevices(User $user): int
    {
        $current = $user->currentAccessToken();

        $query = $user->tokens();

        if ($current instanceof PersonalAccessToken) {
            $query->whereKeyNot($current->getKey());
        }

        return $query->delete();
    }

    /**
     * İsteği yapan jetonun kimliği — "bu cihaz" etiketi için.
     *
     * Oturum çerezi ile gelen bir istekte (stateful ön yüz) ortada bir kişisel
     * erişim jetonu yok; o durumda null dönüyor ve hiçbir satır "bu cihaz"
     * olarak işaretlenmiyor.
     */
    public function currentTokenId(User $user): ?int
    {
        $token = $user->currentAccessToken();

        return $token instanceof PersonalAccessToken ? (int) $token->getKey() : null;
    }

    /**
     * @param  array<int, string> $abilities Boşsa jeton tam yetkili (`*`).
     * @return array{user: User, token: string, expires_at: string|null, abilities: array<int, string>}
     */
    private function issueToken(User $user, ?string $deviceName, array $abilities = []): array
    {
        $name = $this->tokenName($deviceName);

        // Aynı cihazdan yeniden giriş eskisini geçersiz kılıyor. Yoksa her
        // açılışta bir satır daha birikir; kullanıcı "cihazlarım" listesinde
        // aynı telefonu kırk kez görür ve hiçbirini güvenle iptal edemez.
        $user->tokens()->where('name', $name)->delete();

        $expiresAt = $this->expiresAt();
        $granted = $this->resolveAbilities($abilities);

        /** @var NewAccessToken $token */
        $token = $user->createToken($name, $granted, $expiresAt);

        return [
            'user'       => $user,
            'token'      => $token->plainTextToken,
            'expires_at' => $expiresAt?->toIso8601String(),
            // İstemci ne yapabileceğini yanıttan öğreniyor: ekranları buna göre
            // çizip yapamayacağı bir isteği hiç atmıyor.
            'abilities' => $granted,
        ];
    }

    /**
     * İstenen yetkiler — yalnızca daraltabilir.
     *
     * Boş liste "hepsi" demek. Doluysa yalnız tanınan yetkiler alınıyor:
     * uydurma bir dize sessizce jetona yazılmıyor, ve bu yol hiçbir koşulda
     * `*` üretemiyor. Yani parametre bir yetki yükseltme yüzeyi değil.
     *
     * @param  array<int, string> $requested
     * @return array<int, string>
     */
    private function resolveAbilities(array $requested): array
    {
        $allowed = array_values(array_intersect(
            array_map(strval(...), $requested),
            TokenAbility::values(),
        ));

        return $allowed === [] ? ['*'] : $allowed;
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
