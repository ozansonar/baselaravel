<?php

declare(strict_types=1);

namespace App\Session;

use Illuminate\Session\EncryptedStore;

/**
 * Şifreli oturumların geçiş dönemi karşılığı.
 *
 * `SESSION_ENCRYPT=true` olan kurulumlarda çerçeve şifreli depoyu kuruyor;
 * geçişin ona da uygulanması gerekiyor. Çözme işi üst sınıfta kalıyor
 * (`prepareForUnserialize`), bu sınıf yalnız okuma sırasını değiştiriyor.
 */
final class MigratingEncryptedStore extends EncryptedStore
{
    use ReadsLegacySessions;
}
