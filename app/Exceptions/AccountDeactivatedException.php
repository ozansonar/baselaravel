<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Kimlik bilgileri doğru ama hesap pasife alınmış.
 *
 * "Şifre yanlış" ile aynı torbaya atılamaz: kullanıcı doğru şifreyi biliyor ve
 * denemeye devam ederse hiçbir zaman öğrenemeyeceği bir sebeple reddediliyor.
 * Ayrı bir tür olduğu için yanıt da ayrı (403, "hesabınız devre dışı") ve
 * servis katmanı HTTP durum kodu bilmek zorunda kalmıyor.
 */
final class AccountDeactivatedException extends RuntimeException
{
}
