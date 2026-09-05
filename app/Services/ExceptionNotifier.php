<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\SafeUrl;
use App\Enums\NotificationLevel;
use App\Models\ErrorLog;
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

    public function __construct(
        private readonly ErrorLogService $errorLogs,
    ) {}

    public function notify(Throwable $e): void
    {
        // Kayıt önce ve **kısılmadan**: bildirim on dakikada bir gidiyor, ama
        // "bu hata kaç kez oldu" bilgisi ancak her tekrarı sayarak elde
        // ediliyor. Panelin hata listesi bunun üzerine kurulu.
        //
        // Servis kendi içinde her şeyi yutuyor; yine de bir şey sızarsa
        // bildirimin gitmesine engel olmamalı — 500'lerin en sık sebebi
        // veritabanının kendisi ve tam o anda haber vermek en kritik olduğu an.
        $log = $this->errorLogs->record($e);

        try {
            $this->dispatch($e, $log);
        } catch (Throwable) {
            // Zaten hata işlemenin içindeyiz. Buradan bir şey fırlarsa asıl
            // hatanın yerini alır ve loglanan şey yanlış olur.
        }
    }

    private function dispatch(Throwable $e, ?ErrorLog $log): void
    {
        $fingerprint = $this->fingerprint($e);

        if (! $this->firstInWindow($fingerprint)) {
            return;
        }

        $title = 'Sunucu hatası: ' . class_basename($e);

        $context = $this->context($e);

        // Kısma penceresi yüzünden bildirim her tekrarda gitmiyor; kaçıncı kez
        // olduğu bu yüzden mesajın içinde. "Bir kez oldu" ile "bu ay 4.000 kez
        // oldu" arasındaki fark, aciliyetin kendisi.
        if ($log !== null && $log->occurrences > 1) {
            $context['tekrar'] = $log->occurrences . '. kez';
        }

        TelegramNotifier::notifyAdminError(
            $title,
            $context,
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
            // Bildirimden hatanın detayına: yığın izi, kaç kez olduğu ve
            // hangi adreste patladığı orada duruyor.
            actionUrl: $this->panelUrl($log),
        );
    }

    /**
     * Bildirimden hatanın panel sayfasına bağlantı.
     *
     * `route()` burada fırlayabilir —rota önbelleği bozuksa ya da hata rotalar
     * yüklenmeden önce oluştuysa— ve o an bildirimi kaybetmek, bağlantıyı
     * kaybetmekten çok daha pahalı.
     */
    private function panelUrl(?ErrorLog $log): ?string
    {
        if ($log === null) {
            return null;
        }

        return rescue(static fn (): string => route('admin.error-logs.show', $log->id), null, false);
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

        // Telegram sunucunun dışı: sır oraya hiç gitmemeli.
        $context['adres'] = $request->method() . ' ' . SafeUrl::forRequest($request);

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
