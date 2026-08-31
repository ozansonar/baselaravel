<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Bir API jetonunun yapabilecekleri.
 *
 * Varsayılan jeton `*` taşır, yani hepsini yapabilir — mobil uygulama hesabın
 * tamamını yönetiyor ve daraltmanın bir anlamı yok. Yetkiler, jetonun bir
 * uygulamaya değil bir entegrasyona verildiği durum için: bilgi ekranı, rapor
 * aracı, üçüncü taraf bir istemci. Böyle bir yere tam yetkili jeton vermek,
 * onu ele geçiren birine hesabın tamamını vermek demek.
 *
 * Giriş sırasında istenen yetkiler yalnızca DARALTABİLİR: istek hiçbir koşulda
 * `*` üretemez, yalnız bu listeden seçebilir. Yani bu parametre bir yetki
 * yükseltme yüzeyi değil.
 *
 * Çıkış (`logout`) ve cihaz listesi bilerek yetki dışı bırakılmadı — ama çıkış
 * hiçbir yetki istemiyor: bir jeton her zaman kendini iptal edebilmeli, yoksa
 * dar yetkili bir jeton ele geçtiğinde sahibi onu kapatamaz.
 */
enum TokenAbility: string
{
    case ProfileRead = 'profile:read';
    case ProfileWrite = 'profile:write';
    case DevicesManage = 'devices:manage';

    public function label(): string
    {
        return match ($this) {
            self::ProfileRead   => 'Profili görüntüleme',
            self::ProfileWrite  => 'Profili düzenleme',
            self::DevicesManage => 'Oturumları yönetme',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
