<?php

declare(strict_types=1);

/**
 * PHPStan başlamadan önce çalışan tek satırlık iş: LARAVEL_VERSION'ı tanımlamak.
 *
 * ── Neden gerekiyor ──
 *
 * Larastan, stub dosyalarını Laravel sürümüne göre süzüyor
 * (LarastanStubFilesExtension) ve bunu yaparken `LARAVEL_VERSION` sabitini
 * okuyor. Sabiti tanımlayan şey ise Larastan'ın kendi bootstrap dosyası —
 * uygulamayı ayağa kaldırıp `$app->version()` diyen dosya.
 *
 * PHPStan bu ikisini her zaman aynı sırada çalıştırmıyor. Sonuç önbelleği
 * belirli bir durumdayken stub listesi, bootstrap dosyalarından ÖNCE isteniyor;
 * sabit henüz tanımlı olmadığı için analiz
 *
 *     Undefined constant "Larastan\Larastan\LARAVEL_VERSION"
 *
 * diyerek düşüyor. Hata yanıltıcı: uygulamada bir kusur olduğunu düşündürüyor,
 * oysa uygulama gayet iyi açılıyor — `./vendor/bin/phpstan clear-result-cache`
 * "çözüyor" ama sebebi ortadan kaldırmıyor, yalnızca önbelleği o duruma
 * girmemiş hâle getiriyor. Bir sonraki sefer yine çıkıyor.
 *
 * ── Bu dosya ne yapıyor ──
 *
 * `-a` (autoload-file) ile veriliyor, yani PHPStan'ın kabı kurulmadan, dolayısıyla
 * stub listesi istenmeden önce çalışıyor. Sürümü uygulamayı ayağa kaldırmadan,
 * doğrudan çerçevenin sabitinden okuyor.
 *
 * Larastan'ın kendi bootstrap'ı sonradan yine çalışıyor; sabiti
 * `if (! defined(...))` ile koruduğu için burada tanımlanmış olması bir şeyi
 * bozmuyor — yalnızca artık geç kalması mümkün değil.
 */

require_once __DIR__ . '/vendor/autoload.php';

if (! defined('LARAVEL_VERSION')) {
    define('LARAVEL_VERSION', Illuminate\Foundation\Application::VERSION);
}
