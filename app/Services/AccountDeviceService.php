<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * "Cihazlarım" — kullanıcının açık oturumları, iki ayrı yerden.
 *
 * Bir hesaba iki yoldan girilebiliyor ve ikisi ayrı yerde duruyor:
 *
 *   - tarayıcı oturumu → `sessions` tablosu (yalnız database sürücüsünde)
 *   - mobil/harici istemci → `personal_access_tokens` (her kurulumda)
 *
 * API tarafında yalnız jetonlar listeleniyordu (ApiAuthService); telefonundan
 * bakan kişi tarayıcı oturumlarını göremiyordu, tarayıcıdan bakan kişi hiçbir
 * şey göremiyordu. Ekranın anlamlı olması için ikisinin birden listelenmesi
 * gerekiyor: "hesabıma nereden erişiliyor" sorusunun cevabı ikisinin toplamı.
 *
 * Bütün sorgular kullanıcıya bağlı. Başkasının oturum kimliğini yazan biri
 * "yetkin yok" değil "yok" cevabı alıyor — ayrımı söylemek, kimlikleri tek tek
 * deneyerek başka hesapların oturum sayısını öğrenmeye yarardı.
 *
 * @phpstan-type BrowserSession array{id: string, current: bool, ip: ?string, agent: ?string, last_active: Carbon}
 */
final class AccountDeviceService
{
    public function __construct(
        private readonly UserAgentParser $userAgents,
    ) {}

    /**
     * Tarayıcı oturumları yalnız database sürücüsünde bulunabiliyor.
     *
     * Dosya ya da Redis sürücüsünde oturumlar kullanıcıya göre sorgulanamıyor;
     * ekran o zaman bu bölümü hiç göstermiyor. Boş liste göstermek "hiçbir
     * yerden giriş yok" demek olurdu ve bu doğru değil.
     */
    public function browserSessionsSupported(): bool
    {
        return config('session.driver') === 'database';
    }

    /**
     * @return Collection<int, BrowserSession>
     */
    public function browserSessions(User $user, ?string $currentSessionId = null): Collection
    {
        if (! $this->browserSessionsSupported()) {
            return collect();
        }

        return $this->sessionQuery()
            ->where('user_id', $user->getKey())
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn (object $row): array => [
                'id'      => (string) $row->id,
                'current' => $currentSessionId !== null && hash_equals((string) $row->id, $currentSessionId),
                'ip'      => $row->ip_address !== null ? (string) $row->ip_address : null,
                'agent'   => $this->userAgents->label((string) ($row->user_agent ?? '')),
                // Sütun epoch saniye tutuyor; ekranda tarih olarak gösteriliyor.
                'last_active' => Carbon::createFromTimestamp((int) $row->last_activity),
            ])
            ->values();
    }

    /**
     * Mobil ve harici istemciler. Süresi dolmuş jeton listelenmiyor: kullanıcı
     * kapatabileceğini sandığı, aslında zaten kapanmış bir satır görmemeli.
     *
     * @return Collection<int, PersonalAccessToken>
     */
    public function apiTokens(User $user): Collection
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
     * Tek bir tarayıcı oturumunu kapatır.
     *
     * Kullanıcının içinde bulunduğu oturum bilerek kapatılamıyor: "çıkış yap"
     * ayrı bir düğme ve bu ekranda kişinin beklediği şey kendi oturumunun
     * kapanması değil.
     */
    public function revokeBrowserSession(User $user, string $sessionId, ?string $currentSessionId = null): bool
    {
        if (! $this->browserSessionsSupported()) {
            return false;
        }

        if ($currentSessionId !== null && hash_equals($sessionId, $currentSessionId)) {
            return false;
        }

        return $this->sessionQuery()
            ->where('user_id', $user->getKey())
            ->where('id', $sessionId)
            ->delete() > 0;
    }

    public function revokeApiToken(User $user, int $tokenId): bool
    {
        return $user->tokens()->whereKey($tokenId)->delete() > 0;
    }

    /**
     * Bu oturum hariç her şey: öteki tarayıcılar ve bütün jetonlar.
     *
     * Jetonların hepsi düşüyor, "mevcut" olanı korunmuyor: bu metot tarayıcıdan
     * çağrılıyor ve tarayıcının jetonu yok. Mobil uygulamadan gelen aynı istek
     * ApiAuthService::revokeOtherDevices() üzerinden geçiyor, orada kendi
     * jetonu korunuyor.
     *
     * @return int Kapatılan oturum sayısı (tarayıcı + jeton)
     */
    public function revokeOthers(User $user, ?string $currentSessionId = null): int
    {
        $count = 0;

        if ($this->browserSessionsSupported()) {
            $query = $this->sessionQuery()->where('user_id', $user->getKey());

            if ($currentSessionId !== null) {
                $query->where('id', '!=', $currentSessionId);
            }

            $count += $query->delete();
        }

        $count += $user->tokens()->delete();

        // Beni hatırla çerezi kalırsa kapatılan tarayıcı bir sonraki istekte
        // kendini yeniden doğrular ve oturum geri açılır — satırı silmek tek
        // başına yetmiyor.
        //
        // Bunun bir bedeli var: damga hesap genelinde tek olduğu için mevcut
        // tarayıcının "beni hatırla" hakkı da düşüyor. Oturumu açık kalıyor,
        // ama süresi dolduğunda yeniden giriş isteyecek. Ekran bunu yazıyor;
        // alternatifi kapatıldı sanılan bir cihazın geri dönmesiydi.
        $user->forceFill(['remember_token' => null])->saveQuietly();

        return $count;
    }

    private function sessionQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::connection(config('session.connection'))
            ->table((string) config('session.table', 'sessions'));
    }
}
