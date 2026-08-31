<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Services\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;

/**
 * Kimlik doğrulama olaylarını denetim izine yazar.
 *
 * Model gözlemcisi yalnızca satır değişikliklerini görüyor; giriş, çıkış ve
 * başarısız deneme hiçbir satırı değiştirmediği için denetim izinde hiç
 * görünmüyordu. Oysa bir denetimin ilk sorduğu şey bunlar: kim, ne zaman,
 * hangi adresten girdi ve kimin adı kaç kez boşuna denendi.
 *
 * Abone olarak bağlanıyor: hangi olayın hangi metoda gittiği sınıfın kendi
 * içinde duruyor, sağlayıcıda üç ayrı satır olarak dağılmıyor.
 *
 * `Lockout` bilinçli olarak dışarıda: onu `ThrottlesLogins` trait'i fırlatıyor
 * ve bu proje hız sınırını `throttle:login` ara katmanıyla kuruyor, yani olay
 * hiç doğmuyor. Dinlemek ölü kod olurdu.
 */
final class AuditAuthenticationEvents
{
    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class  => 'onLogin',
            Logout::class => 'onLogout',
            Failed::class => 'onFailed',
        ];
    }

    public function onLogin(Login $event): void
    {
        AuditLogger::custom(
            'Giriş yapıldı',
            ['e-posta' => (string) $event->user->getAttribute('email')],
            userId: (int) $event->user->getAuthIdentifier(),
        );
    }

    public function onLogout(Logout $event): void
    {
        if ($event->user === null) {
            return;
        }

        AuditLogger::custom(
            'Çıkış yapıldı',
            ['e-posta' => (string) $event->user->getAttribute('email')],
            userId: (int) $event->user->getAuthIdentifier(),
        );
    }

    /**
     * Başarısız giriş denemesi.
     *
     * Yalnızca denenen adres yazılıyor. `$event->credentials` şifreyi de
     * taşıyor ve denetim izi onu hiçbir biçimde görmemeli — AuditLogger'ın
     * maskesi son savunma, ilk savunma buradaki seçim.
     */
    public function onFailed(Failed $event): void
    {
        AuditLogger::custom(
            'Başarısız giriş denemesi',
            ['e-posta' => (string) ($event->credentials['email'] ?? '(bilinmiyor)')],
            userId: $event->user?->getAuthIdentifier() === null
                ? null
                : (int) $event->user->getAuthIdentifier(),
        );
    }
}
