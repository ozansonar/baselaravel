<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

/**
 * Şifresi doğrulanmış ama henüz oturumu açılmamış kullanıcıyı taşır.
 *
 * İki adım arasında bir yerde durması gerekiyor: kişi şifresini geçti ama
 * içeri girmedi. Oturumda tutuluyor çünkü ikinci adım aynı tarayıcıdan
 * gelmeli — kimliği adres çubuğunda taşımak, bağlantıyı ele geçiren birinin
 * ikinci adımı kendi tarayıcısında açmasına izin verirdi.
 *
 * Beklemenin bir ömrü var: ekranı açık unutulan bir bilgisayarda "şifre
 * doğrulandı" hâli saatlerce durmamalı.
 */
final class TwoFactorChallengeService
{
    private const SESSION_KEY = 'two_factor.pending';

    /** Şifreyi geçtikten sonra ikinci adım için tanınan süre (saniye). */
    private const TTL = 300;

    /**
     * Bir bekleme durumunda kaç yanlış kod denenebilir.
     *
     * Altı hanelik kod bir milyon olasılık demek; bu sayı olmadan yanlış kod
     * bedava oluyordu — bekleme durumu ayakta kalıyor, sayaç tutulmuyordu ve
     * saldırgan aynı oturumda sınırsız deneyebiliyordu. Beş deneme, kodu
     * yanlış giren gerçek kullanıcıya rahat davranırken tahmini de anlamsız
     * kılıyor: sınıra gelen bekleme düşüyor ve şifreden başlamak gerekiyor.
     */
    private const MAX_ATTEMPTS = 5;

    public function start(User $user, bool $remember): void
    {
        session()->put(self::SESSION_KEY, [
            'id'         => $user->getKey(),
            'remember'   => $remember,
            'expires_at' => now()->addSeconds(self::TTL)->getTimestamp(),
            'failures'   => 0,
        ]);
    }

    /**
     * Bekleyen kullanıcının kimliği — hız sınırının kovası bununla kuruluyor.
     *
     * Sınır isteğin gövdesinden okunan bir alana bağlanamaz: ikinci adım formu
     * yalnız `code` gönderiyor, dolayısıyla gövdeye rastgele bir `email`
     * eklemek her seferinde yeni bir kova açıyor ve sınır hiçbir şey
     * tutmuyordu.
     */
    public function pendingId(): ?int
    {
        $pending = session(self::SESSION_KEY);

        return is_array($pending) && isset($pending['id']) ? (int) $pending['id'] : null;
    }

    /**
     * Yanlış kodu sayar; sınıra gelindiğinde beklemeyi düşürür.
     *
     * @return bool bekleme hâlâ ayakta mı
     */
    public function recordFailure(): bool
    {
        $pending = session(self::SESSION_KEY);

        if (! is_array($pending)) {
            return false;
        }

        $pending['failures'] = (int) ($pending['failures'] ?? 0) + 1;

        if ($pending['failures'] >= self::MAX_ATTEMPTS) {
            $this->forget();

            return false;
        }

        session()->put(self::SESSION_KEY, $pending);

        return true;
    }

    /**
     * Bekleyen kullanıcı — süresi dolmuşsa kayıt siliniyor ve null dönüyor.
     */
    public function pendingUser(): ?User
    {
        $pending = session(self::SESSION_KEY);

        if (! is_array($pending) || ! isset($pending['id'], $pending['expires_at'])) {
            return null;
        }

        if ((int) $pending['expires_at'] < now()->getTimestamp()) {
            $this->forget();

            return null;
        }

        $user = User::find($pending['id']);

        // Bekleme sırasında hesap pasife alınmış ya da 2FA kapatılmış
        // olabilir; ikisinde de bu bekleme geçersiz.
        if (! $user instanceof User || ! $user->is_active || ! $user->hasTwoFactorEnabled()) {
            $this->forget();

            return null;
        }

        return $user;
    }

    public function shouldRemember(): bool
    {
        $pending = session(self::SESSION_KEY);

        return is_array($pending) && ($pending['remember'] ?? false) === true;
    }

    public function forget(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
