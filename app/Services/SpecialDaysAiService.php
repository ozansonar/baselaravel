<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SpecialDay;
use App\Services\Concerns\ParsesAiJsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Bir yıla ait hareketli (hicri) Türk dini günlerini AI'dan çeker ve DB'ye kaydeder.
 *
 * Sabit tarihli günler (Yılbaşı, 23 Nisan vb.) ve kural-bazlı günler (Anneler/Babalar Günü)
 * için bu servis kullanılmaz — onlar SpecialDaysProvider içinde formülle hesaplanır.
 *
 * Kullanım: yılda 1 kez (Aralık'ta yeni yıl için), `/admin/ozel-gunler` sayfasında
 * "AI'dan Yıl İçin Hicri Günleri Getir" butonu.
 */
final class SpecialDaysAiService
{
    use ParsesAiJsonResponse;

    public function __construct(
        private readonly AiService $ai,
    ) {}

    /**
     * Verilen yıl için Türkiye'deki dini hareketli günleri AI'dan çekip
     * `special_days` tablosuna ekler/günceller.
     *
     * @return array{success: bool, count: int, message: string, days?: array<int, array<string, mixed>>}
     */
    public function fetchAndStoreForYear(int $year): array
    {
        if ($year < 2024 || $year > 2050) {
            return ['success' => false, 'count' => 0, 'message' => 'Yıl aralığı: 2024-2050'];
        }

        $system = $this->buildSystemPrompt();
        $user   = $this->buildUserPrompt($year);

        $res = $this->ai->generate($system, $user, 0.3);

        if (! ($res['success'] ?? false)) {
            return [
                'success' => false,
                'count'   => 0,
                'message' => 'AI çağrısı başarısız: ' . ($res['message'] ?? 'bilinmeyen hata'),
            ];
        }

        $extracted = $this->extractJsonFromAiResponse((string) ($res['content'] ?? ''));
        if ($extracted === null) {
            return [
                'success' => false,
                'count'   => 0,
                'message' => 'AI yanıtı geçerli JSON formatında değil.',
            ];
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($extracted, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return [
                'success' => false,
                'count'   => 0,
                'message' => 'JSON decode hatası: ' . $e->getMessage(),
            ];
        }

        if (! is_array($decoded)) {
            return ['success' => false, 'count' => 0, 'message' => 'AI yanıtı dizi değil.'];
        }

        $stored = 0;
        $storedDays = [];

        foreach ($decoded as $entry) {
            if (! is_array($entry)) continue;
            $date = trim((string) ($entry['date'] ?? ''));
            $name = trim((string) ($entry['name'] ?? ''));
            if ($date === '' || $name === '') continue;

            // Tarih validasyonu — verilen yılda olmalı
            try {
                $parsed = Carbon::createFromFormat('Y-m-d', $date);
                if ($parsed->year !== $year) continue;
            } catch (\Throwable) {
                continue;
            }

            $slug = Str::slug($name) . '-' . $year;
            $theme = trim((string) ($entry['theme'] ?? ''));
            $category = $this->normalizeCategory((string) ($entry['category'] ?? 'dini'));

            // Idempotent — aynı tarih + slug varsa update, yoksa insert
            $day = SpecialDay::query()
                ->where('date', $parsed->toDateString())
                ->where('slug', $slug)
                ->first();

            if ($day !== null) {
                $day->update([
                    'name'       => $name,
                    'theme'      => $theme !== '' ? $theme : $day->theme,
                    'is_movable' => true,
                ]);
            } else {
                SpecialDay::create([
                    'date'       => $parsed->toDateString(),
                    'name'       => $name,
                    'slug'       => $slug,
                    'category'   => $category,
                    'theme'      => $theme,
                    'source'     => SpecialDay::SOURCE_AI,
                    'is_movable' => true,
                ]);
            }

            $storedDays[] = [
                'date'     => $parsed->toDateString(),
                'name'     => $name,
                'category' => $category,
            ];
            $stored++;
        }

        Log::info('SpecialDaysAiService: AI hicri günler çekildi', [
            'year'  => $year,
            'count' => $stored,
        ]);

        return [
            'success' => true,
            'count'   => $stored,
            'message' => "{$year} yılı için {$stored} hicri gün AI'dan çekilip kaydedildi. Lütfen Diyanet takvimiyle teyit edin.",
            'days'    => $storedDays,
        ];
    }

    private function buildSystemPrompt(): string
    {
        return <<<'EOT'
Sen Türkiye'nin dini takvimi konusunda uzman bir asistansın. Diyanet İşleri Başkanlığı'nın
resmi takvimine göre verilen yıl için hicri/dini günleri JSON formatında listeleyeceksin.

ÇIKTI: SADECE JSON dizi, başka hiçbir şey yazma. Markdown, kod bloğu, açıklama YASAK.

Format:
[
  {
    "date": "YYYY-MM-DD",
    "name": "Gün adı (TR)",
    "category": "dini",
    "theme": "Kısa içerik fikri (1 cümle, marka için story/post temelli)"
  }
]

KAPSAM (verilen yıl için):
- Üç Aylar başlangıcı (Recep ayının 1.günü)
- Regaib Kandili (Recep'in ilk Cuma gecesi)
- Mirac Kandili (27 Recep gecesi)
- Berat Kandili (15 Şaban gecesi)
- Ramazan Başlangıcı (1 Ramazan)
- Kadir Gecesi (27 Ramazan gecesi)
- Ramazan Bayramı (3 gün — 1, 2, 3 Şevval) — her birini ayrı kayıt
- Kurban Bayramı (4 gün — 10, 11, 12, 13 Zilhicce) — her birini ayrı kayıt
- Mevlid Kandili (12 Rebiülevvel)

KURALLAR:
- Tüm tarihler verilen yıl içinde olmalı (yıldan önce/sonra YASAK)
- Hicri ay-gün hesabı Türkiye'nin (Diyanet) kabulünedir, Suudi/Ümmülkurâ değil
- Tarihler tek seferde değil, gerçek hicri gün-ay eşleştirmesiyle
- Bayramların her gününü ayrı kayıt yap (örn. "Ramazan Bayramı 1. Gün", "2. Gün", "3. Gün")
- Theme alanı kısa olmalı, 1 cümle, çiftlik/yöresel ürün markası için uygun
EOT;
    }

    private function buildUserPrompt(int $year): string
    {
        return "Bana {$year} yılı için Türkiye'deki dini hicri günleri JSON formatında listele. "
            . "Kapsam ve format için sistem talimatlarına uy. Ramazan Bayramı 3 gün, Kurban Bayramı 4 gün — "
            . "her birini ayrı kayıt yap. SADECE JSON dizi döndür.";
    }

    private function normalizeCategory(string $raw): string
    {
        $cleaned = mb_strtolower(trim($raw));
        return in_array($cleaned, SpecialDay::CATEGORIES, true) ? $cleaned : SpecialDay::CATEGORY_DINI;
    }
}
