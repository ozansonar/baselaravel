<?php

declare(strict_types=1);

namespace App\Rules;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * users.email için benzersizlik ve uzunluk kuralı, tek yerde.
 *
 * Aynı kural beş ayrı istek sınıfında yazılıydı (kayıt, panelden kullanıcı
 * ekleme/düzenleme, yönetici profili, hesap profili) ve ikisi birden yanlıştı:
 *
 * - Benzersizlik silinmiş satırları da sayıyordu. Soft delete'in anlamı satırın
 *   kayıtta kalması; adresin sonsuza dek işgal edilmesi değil. Silinen bir
 *   kullanıcı aynı adresle geri dönemiyordu
 * - Uzunluk sınırı 255 yazıyordu, oysa sütun 191 karakter
 *   (AppServiceProvider'daki Schema::defaultStringLength). 200 karakterlik bir
 *   adres doğrulamadan geçip veritabanında "Data too long" ile düşüyordu — yani
 *   kullanıcıya doğrulama hatası değil, 500 dönüyordu
 *
 * Beş yerde ayrı yazılınca biri düzeltilip ötekiler geride kalır.
 */
final class UserEmail
{
    /**
     * Sütunun gerçek genişliği.
     *
     * Formlardaki maxSize[] ile birebir aynı olmak zorunda: istemci kuralı
     * sunucudan gevşek olamaz.
     */
    public const MAX_LENGTH = 191;

    /**
     * Yaşayan kullanıcılar arasında benzersiz.
     *
     * Veritabanındaki kısıt da aynı şeyi söylüyor (users_email_active_unique),
     * ama doğrulama olmadan kullanıcı ham bir SQL hatası görürdü.
     *
     * @param int|null $ignoreId Kendi kaydını güncelleyen kullanıcı
     */
    public static function unique(?int $ignoreId = null): Unique
    {
        $rule = Rule::unique('users', 'email')->whereNull('deleted_at');

        return $ignoreId === null ? $rule : $rule->ignore($ignoreId);
    }
}
