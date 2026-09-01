<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Push\FcmAccessToken;
use App\Services\ServiceCredentialResolver;
use App\Services\SettingService;
use App\Support\ServiceCredentials;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * API ve servis anahtarları ekranı.
 *
 * Ekranın tamamı {@see ServiceCredentials} kayıt defterinden çiziliyor: burada
 * hiçbir servisin adı geçmiyor. Yeni bir servis eklemek kayıt defterine bir
 * blok yazmak demek — bu denetleyiciye, görünüme ya da rotaya dokunmak
 * gerekmiyor.
 */
final class ServiceCredentialController extends Controller
{
    public function __construct(
        private readonly SettingService $settings,
        private readonly FcmAccessToken $fcm,
        private readonly ServiceCredentialResolver $resolver,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', Setting::class);

        $values = [];
        $filled = [];

        foreach (ServiceCredentials::fields() as $key => $field) {
            $stored = Setting::getValue($key);

            // Gizli değerler ekrana bir daha basılmıyor: bir kere girildikten
            // sonra ne panelde, ne sayfa kaynağında, ne de tarayıcı geçmişinde
            // görünüyorlar. Ekran yalnız "dolu mu" diyor.
            $values[$key] = $field['secret'] ? '' : (string) ($stored ?? '');

            // "Panel dışında bir değer var mı" sorusunu çözümleyici
            // cevaplıyor. Burada env() çağrılamaz: `config:cache` sonrası —ki
            // üretimin varsayılan durumu bu— env() null döner ve rozet asla
            // ".env" demezdi.
            $filled[$key] = match (true) {
                $stored !== null && $stored !== '' => 'panel',
                $this->resolver->hasFallback($key) => 'env',
                default                            => 'bos',
            };
        }

        return view('admin.service-credentials.index', [
            'groups' => ServiceCredentials::groups(),
            'values' => $values,
            'filled' => $filled,
            // Firebase kurulumunun gerçekten çalışıp çalışmadığı: anahtar
            // girilmiş ama bozuksa ekran bunu söylemeli, yönetici ilk bildirimi
            // göndermeye çalışana kadar beklememeli.
            'fcmReady' => $this->fcm->isConfigured(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('update', Setting::class);

        $fields = ServiceCredentials::fields();

        $request->validate([
            'credentials'   => ['nullable', 'array'],
            'credentials.*' => ['nullable', 'string', 'max:20000'],
        ]);

        DB::transaction(function () use ($request, $fields): void {
            /** @var array<string, mixed> $input */
            $input = (array) $request->input('credentials', []);

            foreach ($fields as $key => $field) {
                if ($field['type'] === 'toggle') {
                    // İşaretlenmemiş kutu istekte hiç yok; varsayılan kapalı
                    // olmalı, yoksa hiçbir düğme kapatılamazdı.
                    $this->store($key, $request->boolean('credentials.' . $key) ? '1' : '0', $field);

                    continue;
                }

                if (! array_key_exists($key, $input)) {
                    continue;
                }

                $value = is_string($input[$key]) ? trim($input[$key]) : '';

                // Gizli alan boş geldiyse dokunulmuyor: ekranda zaten boş
                // görünüyor, kaydet demek onu silmek anlamına gelmemeli.
                if ($field['secret'] && $value === '') {
                    continue;
                }

                $this->store($key, $value, $field);
            }
        });

        // Ayar önbelleği düşüyor; bir sonraki istekte yeni anahtarlar geçerli.
        // Firebase'in erişim jetonu ayrı bir önbellekte duruyor ve eski anahtarla
        // alınmıştı — o da düşmeli, yoksa yeni anahtar bir saat sonra devreye
        // girerdi.
        $this->settings->clearCache();
        $this->fcm->forget();

        return redirect()
            ->route('admin.service-credentials.index')
            ->with('success', 'Servis ayarları kaydedildi ve hemen geçerli oldu.');
    }

    /**
     * @param array<string, mixed> $field
     */
    private function store(string $key, string $value, array $field): void
    {
        // Boşaltılan alan siliniyor, boş dizgeyle kaydedilmiyor: boş bir satır
        // .env yedeğini ezerdi ve "panelde boşsa .env geçerli" kuralı bozulurdu.
        if ($value === '' && $field['type'] !== 'toggle') {
            Setting::query()->whereNull('locale')->where('key', $key)->forceDelete();

            return;
        }

        Setting::setValue($key, $value, 'services', $field['secret'] ? 'password' : 'text');
    }
}
