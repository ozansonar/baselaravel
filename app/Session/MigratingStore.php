<?php

declare(strict_types=1);

namespace App\Session;

use Illuminate\Session\Store;

/**
 * Geçiş dönemindeki oturum deposu.
 *
 * Yazma tarafı çerçevenin kendisi: `serialization` ayarı `json` geçildiği için
 * her kayıt yeni biçimde yazılıyor. Değişen tek şey okuma — eski biçimde
 * yazılmış oturumlar da açılıyor, böylece geçişte kimse oturumundan düşmüyor.
 */
final class MigratingStore extends Store
{
    use ReadsLegacySessions;
}
