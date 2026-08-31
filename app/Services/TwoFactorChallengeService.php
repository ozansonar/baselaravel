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

    public function start(User $user, bool $remember): void
    {
        session()->put(self::SESSION_KEY, [
            'id'         => $user->getKey(),
            'remember'   => $remember,
            'expires_at' => now()->addSeconds(self::TTL)->getTimestamp(),
        ]);
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
