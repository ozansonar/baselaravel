<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ConsentCategory;
use App\Models\Consent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

/**
 * Ziyaretçinin çerez tercihi.
 *
 * Öncesinde hiçbir rıza mekanizması yoktu: Google Analytics ve Tag Manager
 * ayar doluysa koşulsuz yükleniyor, projenin kendi ziyaret kaydı da ilk
 * istekten itibaren IP ve oturum kimliği yazıyordu. IP maskeleme vardı ama
 * 90 gün *sonra* devreye giriyor — yani veri önce toplanıp sonra
 * anonimleştiriliyordu. KVKK'da açık rıza ispat yükü veri sorumlusunda,
 * GDPR kapsamındaki bir ziyaretçi için de analitik çerezler rızadan önce
 * çalışamaz.
 *
 * Tercih iki yerde birden duruyor ve ikisi farklı işe yarıyor:
 *
 *   - **Çerez**, kararı hatırlamak için. Ziyaretçi silebilir; silerse yeniden
 *     sorulur, doğrusu da budur.
 *   - **`consents` tablosu**, ispat için. Ziyaretçinin silemeyeceği yerde,
 *     zaman damgası ve metin sürümüyle.
 */
final class ConsentService
{
    /**
     * Rıza metninin sürümü.
     *
     * Kategoriler ya da açıklamaları değişirse burası artırılır; eski rıza
     * yeni metne verilmiş sayılmaz ve ziyaretçiye bir kez daha sorulur.
     */
    public const VERSION = 1;

    public const COOKIE = 'cerez_tercihi';

    /** Bir yıl: yaygın kabul gören üst sınır. */
    private const COOKIE_MINUTES = 525600;

    /**
     * @var array{version: int, token: string, categories: list<string>}|null
     */
    private ?array $memo = null;

    /**
     * Ziyaretçinin geçerli tercihi; hiç seçmemişse null.
     *
     * @return array{version: int, token: string, categories: list<string>}|null
     */
    public function current(?Request $request = null): ?array
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        $raw = ($request ?? request())->cookie(self::COOKIE);

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return null;
        }

        // Sürüm değiştiyse eski karar yeni metni kapsamıyor.
        if ((int) ($decoded['version'] ?? 0) !== self::VERSION) {
            return null;
        }

        $categories = array_values(array_filter(
            (array) ($decoded['categories'] ?? []),
            static fn (mixed $value): bool => is_string($value) && ConsentCategory::tryFrom($value) !== null,
        ));

        return $this->memo = [
            'version'    => self::VERSION,
            'token'      => (string) ($decoded['token'] ?? ''),
            'categories' => $categories,
        ];
    }

    /**
     * Ziyaretçiye sorulmuş mu?
     */
    public function decided(?Request $request = null): bool
    {
        return $this->current($request) !== null;
    }

    /**
     * Şu kategoriye izin var mı?
     *
     * Zorunlu kategori her zaman açık. Karar verilmemişse **hiçbir isteğe
     * bağlı kategori açık değildir** — varsayılan "hayır", çünkü rıza
     * alınmadan çalışmak tam olarak kapatmaya çalıştığımız şey.
     */
    public function allows(ConsentCategory $category, ?Request $request = null): bool
    {
        if ($category->isRequired()) {
            return true;
        }

        $current = $this->current($request);

        return $current !== null && in_array($category->value, $current['categories'], true);
    }

    /**
     * Tercihi kaydet: kayda geç ve çerezi kuyruğa al.
     *
     * @param  list<string> $categories ziyaretçinin seçtiği isteğe bağlı kategoriler
     * @return array{consent: Consent, cookie: SymfonyCookie}
     */
    public function store(array $categories, Request $request): array
    {
        $chosen = $this->sanitise($categories);

        $existing = $this->current($request);
        $token = $existing['token'] ?? '';

        if ($token === '' || ! Str::isUuid($token)) {
            $token = (string) Str::uuid();
        }

        $consent = Consent::create([
            'token'      => $token,
            'categories' => $chosen,
            'version'    => self::VERSION,
            'user_id'    => $request->user()?->getKey(),
            'ip_address' => $request->ip(),
            'user_agent' => mb_strimwidth((string) $request->userAgent(), 0, 500, ''),
            'url'        => mb_strimwidth((string) $request->headers->get('referer', $request->fullUrl()), 0, 500, ''),
        ]);

        $payload = json_encode([
            'version'    => self::VERSION,
            'token'      => $token,
            'categories' => $chosen,
        ], JSON_UNESCAPED_SLASHES);

        // Tercihin kendisi zorunlu bir çerez: onsuz her sayfada yeniden
        // sorulurdu.
        //
        // httpOnly ve şifreli kalıyor. Kutuların hangi durumda açılacağını
        // sunucu basıyor, yani JavaScript'in bu çerezi okumasına gerek yok —
        // okuyabilseydi hem şifrelemeyi kapatmak hem de değeri betiklere
        // açmak gerekirdi, ikisi de gereksiz risk.
        $cookie = Cookie::make(
            name: self::COOKIE,
            value: (string) $payload,
            minutes: self::COOKIE_MINUTES,
        );

        $this->memo = [
            'version'    => self::VERSION,
            'token'      => $token,
            'categories' => $chosen,
        ];

        // Denetim izine yazılmıyor: kararın kaydı zaten `consents` tablosunda
        // ve orası ispat için doğru yer. Her ziyaretçinin tıklaması denetim
        // izine düşseydi iz kendi gürültüsünde boğulurdu — içerik modellerini
        // izlemeye almama gerekçesinin aynısı.
        return ['consent' => $consent, 'cookie' => $cookie];
    }

    /**
     * Gelen listeyi bilinen ve isteğe bağlı kategorilerle sınırla.
     *
     * Zorunlu kategori dışarıda bırakılıyor: kaydedilen şey ziyaretçinin
     * *kararı*, her hâlükârda açık olan bir kategoriyi karar gibi saklamak
     * kaydı yanıltıcı yapardı.
     *
     * @param  list<string> $categories
     * @return list<string>
     */
    private function sanitise(array $categories): array
    {
        $allowed = array_map(
            static fn (ConsentCategory $case): string => $case->value,
            ConsentCategory::optional(),
        );

        return array_values(array_unique(array_filter(
            $categories,
            static fn (mixed $value): bool => is_string($value) && in_array($value, $allowed, true),
        )));
    }
}
