<?php

declare(strict_types=1);

namespace App\Rules;

/**
 * Ziyaretçiden alınan e-posta adresinin biçim kuralı, tek yerde.
 *
 * Aynı kural beş ayrı istek sınıfında yazılıydı (iletişim formu — web ve API,
 * bülten aboneliği, panelden kullanıcı ekleme ve düzenleme). Beş yerde ayrı
 * yazılınca biri değişip ötekiler geride kalır.
 *
 * `dns` bölümü adresin alan adının gerçekten posta alabildiğine bakar (MX,
 * yoksa A/AAAA kaydı). Uydurma alan adlarıyla gelen form spam'ini kaynağında
 * eleyen şey bu; üretimde kalması gerekiyor.
 *
 * Ama sınamada kalamaz: `dns` canlı bir DNS sorgusu demek. Bu suite'i internet
 * bağlantısına —üstelik üçüncü tarafların DNS kayıtlarına— bağımlı kılıyordu ve
 * nitekim bağladı: testlerin kullandığı `ornek.com` alan adının kayıtları bir
 * gün düştü ve kodda hiçbir şey değişmemişken beş test birden kırmızıya döndü.
 * Ağa çıkamayan bir makinede (uçak, kapalı CI ağı) suite hiç geçmiyordu.
 *
 * Bu yüzden sınamada yalnız biçim denetleniyor. Üretim kuralının `dns`
 * taşımaya devam ettiğini {@see \Tests\Feature\EmailValidationTest} bekçiliyor —
 * yoksa bu muafiyet, kuralın sessizce gevşetilmesinin kapısı olurdu.
 */
final class EmailAddress
{
    /**
     * Üretimde geçerli olan kural.
     *
     * Sınama bu sabite bakıyor: `dns` buradan düşerse sınama kırılır.
     */
    public const RULE = 'email:rfc,dns';

    /**
     * Sınamada kullanılan kural — ağa çıkmayan hâli.
     */
    public const RULE_WITHOUT_DNS = 'email:rfc';

    /**
     * Biçim kuralı — adresin yazımı ve alan adının posta alabilmesi.
     *
     * Var olan bir hesabın adresi sorulurken (giriş, şifre sıfırlama) ve
     * panelden kullanıcı eklenirken bu yeterli.
     */
    public static function rule(): string
    {
        return app()->runningUnitTests() ? self::RULE_WITHOUT_DNS : self::RULE;
    }

    /**
     * Ziyaretçiden **ilk kez** alınan bir adresin tam kural kümesi.
     *
     * Biçim kuralına tek kullanımlık adres süzgeci ekleniyor. İkisi farklı
     * şeylere bakıyor ve biri ötekinin yerini tutmuyor: `dns` uydurma alan
     * adlarını eliyor, ama tek kullanımlık sağlayıcıların alan adları gerçek
     * ve MX kayıtları çalışıyor — o süzgeçten sorunsuz geçiyorlar.
     *
     * Nerede kullanılıyor: üyelik kaydı, bülten aboneliği, iletişim formu,
     * yorum ve hesap sahibinin adres değiştirmesi. Yani adresin ileride
     * ulaşılabilir olmasının önemli olduğu her yer.
     *
     * Nerede **kullanılmıyor** ve neden:
     *  - Giriş ve şifre sıfırlama: adres zaten kayıtlı bir hesabı gösteriyor.
     *    Burada engellemek, kaydı bir şekilde oluşmuş kişiyi kendi hesabından
     *    kilitlemek olurdu.
     *  - Panelden kullanıcı ekleme/düzenleme: yönetici ne yaptığını biliyor;
     *    geçici bir hesabı bilerek açabilmeli. Kural bir engel değil, spam
     *    süzgeci — ve yönetici spam değil.
     *
     * @return list<string|NotDisposableEmail>
     */
    public static function rules(): array
    {
        return [self::rule(), new NotDisposableEmail()];
    }
}
