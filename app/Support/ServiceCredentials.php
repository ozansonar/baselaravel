<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Üçüncü taraf servislerin panelden yönetilen kimlik bilgileri.
 *
 * Bu dosya tek kaynak: ekranı çizen de, değeri `config()` üzerine yazan da,
 * neyin gizli olduğuna karar veren de buradan okuyor. Yeni bir servis eklemek
 * buraya bir blok yazmak demek — ne yeni bir görünüm, ne yeni bir denetleyici,
 * ne de yeni bir sağlayıcı gerekiyor.
 *
 * Her alan dört soruyu cevaplıyor:
 *
 *  - `config`  → değerin hangi config yoluna yazılacağı. Çalışma zamanında
 *                {@see \App\Services\ServiceCredentialResolver} bunu uyguluyor,
 *                yani servisler kodda hiçbir şey bilmeden panelden besleniyor.
 *  - `env`     → panelde boş bırakıldığında düşülecek .env anahtarı. Geriye
 *                dönük uyumluluk: bugüne kadar .env'e yazmış kurulumlar
 *                bozulmuyor.
 *  - `secret`  → değer şifreli saklanıyor ve ekrana bir daha basılmıyor.
 *  - `help`    → anahtarın nereden alınacağı. Kurulumu altı ay sonra yapan
 *                kişi (ya da başka biri) konsolda kaybolmasın diye adım adım.
 *
 * Mail ve Telegram bilerek burada değil: ikisi de yalnız kimlik bilgisinden
 * ibaret değil (bağlantı testi, gönderim limitleri, mail teması, bildirim
 * seviyesi) ve kendi sekmelerinde bütün hâlinde duruyorlar. Analitik
 * kimlikleri de burada değil: onlar üçüncü taraf *anahtarı* değil site
 * ayarı ve Ayarlar → Genel altında duruyorlar.
 *
 * Kural şu: bir ayar iki formdan yönetilmemeli. İkisinin sessizce ayrışmasının
 * en kısa yolu budur.
 */
final class ServiceCredentials
{
    /**
     * @return array<string, array{
     *     label: string,
     *     icon: string,
     *     description: string,
     *     doc: array{url: string, label: string}|null,
     *     fields: array<string, array{
     *         label: string,
     *         config: string,
     *         env: string,
     *         type: string,
     *         secret: bool,
     *         placeholder: string,
     *         help: string,
     *     }>
     * }>
     */
    public static function groups(): array
    {
        return [
            'google' => [
                'label'       => 'Google ile Giriş',
                'icon'        => 'bi-google',
                'description' => 'Mobil uygulamanın Google hesabıyla giriş yapabilmesi için. '
                    . 'Boş bırakılırsa bu giriş yolu kapalı kalır.',
                'doc' => [
                    'url'   => 'https://console.cloud.google.com/apis/credentials',
                    'label' => 'Google Cloud Console → Kimlik Bilgileri',
                ],
                'fields' => [
                    'google_client_ids' => [
                        'label'       => 'OAuth İstemci Kimlikleri',
                        'config'      => 'services.google.client_ids',
                        'env'         => 'GOOGLE_CLIENT_IDS',
                        'type'        => 'textarea',
                        'secret'      => false,
                        'placeholder' => "123-ios.apps.googleusercontent.com,\n123-android.apps.googleusercontent.com",
                        'help'        => 'Google Cloud Console → API ve Servisler → Kimlik Bilgileri → '
                            . '"OAuth 2.0 İstemci Kimlikleri". iOS, Android ve web için ayrı ayrı '
                            . 'istemci oluşturun ve üçünün kimliğini de buraya virgülle ayırarak yapıştırın — '
                            . 'üçü de aynı hesaba giriş yapar. İstemci sırrı (client secret) gerekmiyor: '
                            . 'sunucu jetonun imzasını doğruluyor, jeton almıyor.',
                    ],
                ],
            ],

            'apple' => [
                'label'       => 'Apple ile Giriş',
                'icon'        => 'bi-apple',
                'description' => 'App Store kuralı: başka bir sosyal giriş sunan uygulamalarda '
                    . '"Sign in with Apple" zorunlu. Boş bırakılırsa bu giriş yolu kapalı kalır.',
                'doc' => [
                    'url'   => 'https://developer.apple.com/account/resources/identifiers/list',
                    'label' => 'Apple Developer → Identifiers',
                ],
                'fields' => [
                    'apple_client_ids' => [
                        'label'       => 'İstemci Kimlikleri (Bundle / Services ID)',
                        'config'      => 'services.apple.client_ids',
                        'env'         => 'APPLE_CLIENT_IDS',
                        'type'        => 'textarea',
                        'secret'      => false,
                        'placeholder' => "com.sirket.uygulama,\ncom.sirket.uygulama.web",
                        'help'        => 'Mobil uygulama için Xcode\'daki **Bundle ID** '
                            . '(ör. com.sirket.uygulama), web girişi de kullanacaksanız Apple Developer '
                            . '→ Identifiers → Services IDs altındaki kimlik. Virgülle birden çok '
                            . 'yazılabilir.',
                    ],
                ],
            ],

            'firebase' => [
                'label'       => 'Firebase — Mobil Bildirim',
                'icon'        => 'bi-bell',
                'description' => 'Panelden gönderilen duyuruların telefonlara ulaşması için. '
                    . 'Boş bırakılırsa cihaz jetonları kaydedilmeye devam eder ama bildirim gitmez.',
                'doc' => [
                    'url'   => 'https://console.firebase.google.com/',
                    'label' => 'Firebase Console → Proje ayarları → Hizmet hesapları',
                ],
                'fields' => [
                    'fcm_service_account' => [
                        'label'       => 'Servis Hesabı Anahtarı (JSON)',
                        'config'      => 'push.fcm.credentials_json',
                        'env'         => '',
                        'type'        => 'textarea',
                        'secret'      => true,
                        'placeholder' => '{ "type": "service_account", "project_id": "...", ... }',
                        'help'        => 'Firebase Console → Proje ayarları → **Hizmet hesapları** → '
                            . '"Yeni özel anahtar oluştur" düğmesi bir JSON dosyası indirir. '
                            . 'O dosyanın **içeriğini** açıp olduğu gibi buraya yapıştırın. '
                            . 'Proje kimliği JSON\'un içinde, ayrıca yazmanıza gerek yok. '
                            . 'Değer şifreli saklanır ve bir daha ekrana basılmaz.',
                    ],
                    'push_driver' => [
                        'label'       => 'Gönderim Açık',
                        'config'      => 'push.driver',
                        'env'         => 'PUSH_DRIVER',
                        'type'        => 'toggle',
                        // Bu düğme mantıksal değil sürücü adı yazıyor: config
                        // 'fcm' ya da 'null' bekliyor.
                        'on'          => 'fcm',
                        'off'         => 'null',
                        'secret'      => false,
                        'placeholder' => '',
                        'help'        => 'Kapalıyken cihaz jetonları yine kaydedilir ama bildirim '
                            . 'gönderilmez ve bu log\'a düşer — sessizce kaybolmaz. '
                            . 'Yukarıdaki anahtarı girmeden açmanın anlamı yok.',
                    ],
                ],
            ],

            'recaptcha' => [
                'label'       => 'reCAPTCHA',
                'icon'        => 'bi-shield-check',
                'description' => 'İletişim formu, yorum ve kayıt ekranlarını bot gönderimlerine karşı '
                    . 'korur. Boş bırakılırsa doğrulama hiç istenmez.',
                'doc' => [
                    'url'   => 'https://www.google.com/recaptcha/admin',
                    'label' => 'Google reCAPTCHA Yönetim Konsolu',
                ],
                'fields' => [
                    'recaptcha_enabled' => [
                        'label'       => 'reCAPTCHA Açık',
                        // config yolu yok: RecaptchaService bu ayarı doğrudan
                        // okuyor. Kayıt defteri hem paneli çiziyor hem config
                        // köprüsü kuruyor; her alanın ikincisine ihtiyacı yok.
                        'config'      => '',
                        'env'         => '',
                        'type'        => 'toggle',
                        'on'          => '1',
                        'off'         => '0',
                        'secret'      => false,
                        'placeholder' => '',
                        'help'        => 'Kapalıyken formlar doğrulama kutusu göstermez. '
                            . 'Anahtarlar girilmeden açmak formu kırar.',
                    ],
                    'recaptcha_site_key' => [
                        'label'       => 'Site Anahtarı (Site Key)',
                        'config'      => 'services.recaptcha.site_key',
                        'env'         => 'RECAPTCHA_SITE_KEY',
                        'type'        => 'text',
                        'secret'      => false,
                        'placeholder' => '6Lc...',
                        'help'        => 'google.com/recaptcha/admin adresinden **reCAPTCHA v2 '
                            . '("Ben robot değilim" kutusu)** türünde bir site kaydedin. '
                            . 'Alan adı listesine sitenizin adresini ekleyin. Verilen iki anahtardan '
                            . 'ilki budur ve sayfanın kaynağında görünür — gizli değildir.',
                    ],
                    'recaptcha_secret_key' => [
                        'label'       => 'Gizli Anahtar (Secret Key)',
                        'config'      => 'services.recaptcha.secret_key',
                        'env'         => 'RECAPTCHA_SECRET_KEY',
                        'type'        => 'password',
                        'secret'      => true,
                        'placeholder' => '',
                        'help'        => 'Aynı ekrandaki ikinci anahtar. Sunucu, ziyaretçinin '
                            . 'kutuyu gerçekten işaretlediğini bununla doğruluyor. '
                            . 'Kimseyle paylaşmayın; şifreli saklanır ve bir daha ekrana basılmaz.',
                    ],
                ],
            ],

        ];
    }

    /**
     * Bütün alanlar, ayar anahtarına göre düzleştirilmiş.
     *
     * @return array<string, array{label: string, config: string, env: string, type: string, secret: bool, placeholder: string, help: string, group: string}>
     */
    public static function fields(): array
    {
        $flat = [];

        foreach (self::groups() as $groupKey => $group) {
            foreach ($group['fields'] as $key => $field) {
                $flat[$key] = $field + ['group' => $groupKey];
            }
        }

        return $flat;
    }

    /**
     * Şifreli saklanan ayar anahtarları.
     *
     * @return list<string>
     */
    public static function secretKeys(): array
    {
        return array_keys(array_filter(self::fields(), static fn (array $field): bool => $field['secret']));
    }

    public static function isSecret(string $key): bool
    {
        return in_array($key, self::secretKeys(), true);
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::fields());
    }
}
