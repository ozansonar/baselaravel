<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * API girişinde şifre doğru ama ikinci adım eksik.
 *
 * Ayrı bir istisna, çünkü istemcinin buna vereceği tepki "giriş başarısız"dan
 * bambaşka: kullanıcıya kod ekranını açıp aynı isteği kodla tekrarlaması
 * gerekiyor. 401 ile karışsaydı uygulama kişiyi "şifren yanlış" diye geri
 * çevirirdi.
 *
 * Kodun yanlış olması ile hiç gönderilmemiş olması da ayrı: ilkinde istemci
 * hata gösteriyor, ikincisinde sessizce ekranı açıyor.
 */
final class TwoFactorRequiredException extends Exception
{
    public function __construct(
        public readonly bool $invalidCode = false,
    ) {
        parent::__construct('Two factor authentication required.');
    }
}
