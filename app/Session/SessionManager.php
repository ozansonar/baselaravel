<?php

declare(strict_types=1);

namespace App\Session;

use Illuminate\Session\SessionManager as BaseSessionManager;

/**
 * Oturum deposunu `session.serialization` ayarına göre kurar.
 *
 * Çerçeve iki değer tanıyor: `php` (varsayılan) ve `json`. Bu kit üçüncü bir
 * değer ekliyor: **`migrate`**.
 *
 * | Ayar | Yazma | Okuma |
 * |---|---|---|
 * | `php` | serialize | serialize |
 * | `migrate` | JSON | JSON, tutmazsa serialize |
 * | `json` | JSON | JSON |
 *
 * `migrate`, çalışan bir kurulumu bakım penceresi açmadan taşımak için var:
 * açık oturumlar bir sonraki isteklerinde sessizce yeni biçime geçiyor.
 * Geçiş tamamlandığında `json`'a alınması gerekiyor — `migrate` açıkken
 * `unserialize()` yolu hâlâ duruyor ve geçişin asıl amacı onu kapatmak.
 *
 * @see \App\Session\ReadsLegacySessions
 */
final class SessionManager extends BaseSessionManager
{
    /** Geçiş modunun ayardaki adı. */
    public const MIGRATE = 'migrate';

    /**
     * @param  \SessionHandlerInterface  $handler
     * @return \Illuminate\Session\Store
     */
    protected function buildSession($handler)
    {
        if (! $this->migrating()) {
            return parent::buildSession($handler);
        }

        // Geçiş modunda çerçeveye `json` deniyor: yazma yeni biçimde olsun.
        // Eski biçimi okuma yeteneği depodan geliyor.
        return $this->config->get('session.encrypt')
            ? new MigratingEncryptedStore(
                $this->config->get('session.cookie'),
                $handler,
                $this->container['encrypter'],
                null,
                'json',
            )
            : new MigratingStore(
                $this->config->get('session.cookie'),
                $handler,
                null,
                'json',
            );
    }

    public function migrating(): bool
    {
        return $this->config->get('session.serialization') === self::MIGRATE;
    }
}
