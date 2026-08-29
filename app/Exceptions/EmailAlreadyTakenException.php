<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Çöpteki bir kullanıcı, adresi bu arada başkasına geçtiği için geri
 * yüklenemiyor.
 *
 * E-posta artık yalnız yaşayan kullanıcılar arasında benzersiz; silinen bir
 * kullanıcının adresi serbest kalıyor ve yeni biri onu alabiliyor. O adres
 * alındıktan sonra eski kaydı geri yüklemek iki canlı kullanıcıyı aynı adrese
 * bindirir — veritabanı zaten reddediyor, ama yakalanmasaydı yönetici ham bir
 * SQL hatası görürdü.
 */
final class EmailAlreadyTakenException extends RuntimeException
{
    public static function for(string $email): self
    {
        return new self(
            "Bu kullanıcının e-posta adresi ({$email}) silindikten sonra başka bir hesaba verilmiş. "
                . 'Geri yüklemek için önce adreslerden birini değiştirin.',
        );
    }
}
