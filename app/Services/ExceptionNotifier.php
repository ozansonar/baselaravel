<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationLevel;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * İşlenmeyen bir hata olduğunda yöneticiye haber verir.
 *
 * `bootstrap/app.php` içindeki `withExceptions()` bloğu boştu: canlıda 500
 * veren bir sayfa yalnızca `storage/logs` altına düşüyordu ve kimse bakmıyordu.
 * Bir kullanıcı şikâyet edene kadar sitenin bir bölümü günlerce kırık
 * kalabilirdi — üstelik projede çalışan bir bildirim kanalı zaten vardı,
 * yalnızca yedekleme ve birkaç servis onu kullanıyordu.
 *
 * Beklenen hatalar buraya hiç gelmiyor: Laravel 404, 403, 419, 429, doğrulama
 * ve kimlik hatalarını raporlamadan önce eliyor (`Handler::$internalDontReport`).
 * Yani buradaki her mesaj gerçekten beklenmedik bir şey.
 *
 * **Bilinen sınır:** Telegram ayarları `settings` tablosundan okunuyor, yani
 * veritabanının kendisi düştüğünde bildirim gönderilemez. O senaryoda geriye
 * dosya logu ve Sistem Sağlık ekranı kalıyor. Ayarları veritabanı düşse de
 * okunabilir tutmak isteyen kurulum `CACHE_STORE=file` kullanabilir.
 */
final class ExceptionNotifier
{
    /**
     * Aynı hata için iki bildirim arasında en az bu kadar süre geçer.
     *
     * Sıcak bir sayfadaki döngüsel hata dakikada yüzlerce kez tekrar edebilir;
     * anahtar hatanın türü ve satırı olduğu için farklı bir hata beklemeden
     * haber veriyor.
     */
    public const THROTTLE_MINUTES = 10;

    public function notify(Throwable $e): void
    {
        try {
            $this->dispatch($e);
        } catch (Throwable) {
            // Zaten hata işlemenin içindeyiz. Buradan bir şey fırlarsa asıl
            // hatanın yerini alır ve loglanan şey yanlış olur.
        }
    }

    private function dispatch(Throwable $e): void
    {
        $fingerprint = $this->fingerprint($e);

        if (! $this->firstInWindow($fingerprint)) {
            return;
        }

        $title = 'Sunucu hatası: ' . class_basename($e);

        TelegramNotifier::notifyAdminError(
            $title,
            $this->context($e),
            null,
            '🚨',
            cacheKey: $fingerprint,
        );

        NotificationCenter::send(
            type: 'exception',
            title: $title,
            message: $this->summary($e),
            level: NotificationLevel::Critical,
            icon: 'bi-bug-fill',
        );
    }

    /**
     * Aynı hata mı?
     *
     * Tür + dosya + satır: aynı kusur farklı isteklerde farklı mesaj üretse
     * bile (kimlik, adres) tek bir hata olarak sayılıyor.
     */
    private function fingerprint(Throwable $e): string
    {
        return 'exception_notice:' . md5($e::class . '|' . $e->getFile() . ':' . $e->getLine());
    }

    /**
     * Bu pencerede ilk kez mi görülüyor?
     *
     * `add` yazma ile okumayı tek işlemde yapıyor: aynı anda gelen iki istek
     * iki bildirim üretemiyor. Önbellek okunamıyorsa bildirim susturulmuyor —
     * susmak, haber vermemek demek olurdu.
     */
    private function firstInWindow(string $fingerprint): bool
    {
        try {
            return Cache::add($fingerprint, true, now()->addMinutes(self::THROTTLE_MINUTES));
        } catch (Throwable) {
            return true;
        }
    }

    /**
     * @return array<string, string>
     */
    private function context(Throwable $e): array
    {
        $context = [
            'hata'  => $e->getMessage() !== '' ? $e->getMessage() : '(mesaj yok)',
            'konum' => $this->location($e),
        ];

        if (app()->runningInConsole()) {
            $context['kaynak'] = 'Konsol / zamanlanmış görev';

            return $context;
        }

        $request = request();

        $context['adres'] = $request->method() . ' ' . $request->fullUrl();

        $userId = rescue(static fn (): ?int => auth()->id(), null, false);

        if ($userId !== null) {
            $context['kullanici'] = '#' . $userId;
        }

        return $context;
    }

    private function summary(Throwable $e): string
    {
        $message = $e->getMessage() !== '' ? $e->getMessage() : $e::class;

        return $message . ' — ' . $this->location($e);
    }

    /**
     * Dosya yolu proje köküne göre: mutlak yol satıra sığmıyor ve hosting
     * kullanıcı adını mesaja taşıyor.
     */
    private function location(Throwable $e): string
    {
        $file = $e->getFile();
        $root = base_path() . DIRECTORY_SEPARATOR;

        if (str_starts_with($file, $root)) {
            $file = substr($file, strlen($root));
        }

        return $file . ':' . $e->getLine();
    }
}
