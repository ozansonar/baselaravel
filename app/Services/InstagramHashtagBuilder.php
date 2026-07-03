<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogPost;

/**
 * Konu-bağımlı, sıkı filtreli Instagram hashtag üretici.
 *
 * Akış (4 katman):
 *   1. AI    → InstagramCaptionAiService::generateFromTopic ile blog konusundan
 *   2. Marka → Sabit firmaya özel etiketler
 *   3. Kategori → Blog kategorisi slug'ı (whitelist'e tam eşleşmiyorsa düşer)
 *   4. Şehir → Blog title'ında geçen TR il adları (en fazla 3)
 *
 * Filtre pipeline (her tag iki kontrolden geçer):
 *   ❌ Blacklist → eşleşirse REDDEDİLİR (kelime substring match)
 *   ✓ Whitelist → en az 1 tematik kökle örtüşmüyorsa REDDEDİLİR
 *
 * Eşleşme diacritics-insensitive: 'köytereyağı' → 'koy' / 'tereyagi' bulur.
 * Tüm tag'ler lowercase ASCII'ye normalize edilir.
 *
 * Sonuç: max 30 unique tag (Instagram limiti), tamamen konu-bağımlı.
 */
final class InstagramHashtagBuilder
{
    /** @var list<string> */
    private const array BRAND_TAGS = [
        'orhanbabaninciftligi',
        'çiftlik',
        'doğal',
        'köy',
        'köyürünleri',
        'organik',
        'çorum',
    ];

    /** @var array<string,string> */
    private const array DIACRITICS_MAP = [
        'ç' => 'c', 'Ç' => 'c',
        'ğ' => 'g', 'Ğ' => 'g',
        'ı' => 'i', 'I' => 'i', 'İ' => 'i', 'i' => 'i',
        'ö' => 'o', 'Ö' => 'o',
        'ş' => 's', 'Ş' => 's',
        'ü' => 'u', 'Ü' => 'u',
        'â' => 'a', 'Â' => 'a',
        'î' => 'i', 'Î' => 'i',
        'û' => 'u', 'Û' => 'u',
    ];

    public function __construct(
        private readonly InstagramCaptionAiService $captionAi,
    ) {}

    /**
     * Blog yazısı için hashtag listesi üret.
     *
     * @param  list<string>|null  $preFetchedAiHashtags  Çağıran AI'ı zaten çağırmış
     *                                                   ve hashtag almışsa burada
     *                                                   geç → çift AI çağrısı önlenir.
     *                                                   null → builder kendi çağırır.
     * @return list<string>  Lowercase normalize, # olmadan, max 30 tag.
     */
    public function buildForBlog(BlogPost $post, ?array $preFetchedAiHashtags = null): array
    {
        $domain = (array) config('instagram-hashtag-domain', []);
        /** @var list<string> $whitelist */
        $whitelist = (array) ($domain['whitelist'] ?? []);
        /** @var list<string> $cities */
        $cities = (array) ($domain['cities'] ?? []);
        /** @var list<string> $blacklist */
        $blacklist = (array) ($domain['blacklist'] ?? []);

        $candidates = [];

        // ── Layer 1: AI hashtag'ler
        // Çağıran zaten almış olabilir (çift API call önleme); yoksa biz çağırırız.
        if ($preFetchedAiHashtags !== null) {
            $candidates = array_merge($candidates, $preFetchedAiHashtags);
        } else {
            try {
                $candidates = array_merge($candidates, $this->fetchAiHashtags($post));
            } catch (\Throwable) {
                // AI fail → diğer katmanlara güven
            }
        }

        // ── Layer 2: Brand (her zaman dahil — whitelist'te zaten kayıtlı)
        $candidates = array_merge($candidates, self::BRAND_TAGS);

        // ── Layer 3: Category (kategori adı / slug)
        $post->loadMissing('category');
        if ($post->category && ! empty($post->category->slug)) {
            $catTag = $this->slugToTag((string) $post->category->slug);
            if ($catTag !== null) {
                $candidates[] = $catTag;
            }
        }

        // ── Layer 4: City detection (sadece blog title'ında geçenler)
        $detectedCities = $this->detectCities((string) $post->title, $cities);
        $candidates = array_merge($candidates, $detectedCities);

        // ── Filtre pipeline
        // Şehir tag'leri özel mantık: AI rastgele "izmir" yollasa bile blog'da
        // tespit edilmediyse REDDEDİLİR. Sadece detected listedeki şehirler geçer.
        // İSTİSNA: BRAND_TAGS (marka) bu kuraldan muaf — firma şehri (çorum)
        // her durumda dahil edilir, blog title'ında geçmese bile.
        $detectedCitiesAscii = array_map(fn ($c) => $this->stripDiacritics($c), $detectedCities);
        $allCitiesAscii = array_map(fn ($c) => $this->stripDiacritics($c), $cities);
        $brandTagsAscii = array_map(
            fn ($t) => $this->stripDiacritics(mb_strtolower($t, 'UTF-8')),
            self::BRAND_TAGS,
        );

        $clean = [];
        foreach ($candidates as $raw) {
            $tag = $this->normalize((string) $raw);
            if ($tag === '' || mb_strlen($tag, 'UTF-8') < 2 || mb_strlen($tag, 'UTF-8') > 60) {
                continue;
            }
            if ($this->isBlacklisted($tag, $blacklist)) {
                continue;
            }

            $tagAscii = $this->stripDiacritics($tag);
            $isBrandTag = in_array($tagAscii, $brandTagsAscii, true);

            // Şehir adı kontrolü — sadece blog'da tespit edilenler veya marka şehir
            if (! $isBrandTag
                && in_array($tagAscii, $allCitiesAscii, true)
                && ! in_array($tagAscii, $detectedCitiesAscii, true)
            ) {
                continue; // Marka olmayan + blog'da geçmeyen şehir → red
            }

            if (! $this->isWhitelisted($tag, $whitelist, $cities)) {
                continue;
            }
            $clean[$tag] = true; // unique
        }

        return array_slice(array_keys($clean), 0, 30);
    }

    /**
     * AI'dan blog title + body context'iyle hashtag iste.
     *
     * @return list<string>
     */
    private function fetchAiHashtags(BlogPost $post): array
    {
        // Topic context: title + ilk 800 char body (HTML temiz)
        $body = trim(strip_tags((string) ($post->body ?? '')));
        $bodyExcerpt = mb_substr($body, 0, 800, 'UTF-8');
        $topic = trim((string) $post->title) . ($bodyExcerpt !== '' ? "\n\n" . $bodyExcerpt : '');

        $result = $this->captionAi->generateFromTopic($topic);
        if (! ($result['success'] ?? false)) {
            return [];
        }

        return (array) ($result['hashtags'] ?? []);
    }

    /**
     * Tag normalize: lowercase + # temizle + boşluk temizle + diacritics korunur.
     * (Diacritics filter sırasında ASCII'ye çevrilip karşılaştırılır; tag kendisi
     *  Türkçe karakterli kalır → IG'de doğru görünüm.)
     */
    private function normalize(string $tag): string
    {
        $tag = preg_replace('/^#+/u', '', mb_strtolower(trim($tag), 'UTF-8'));
        $tag = preg_replace('/\s+/u', '', (string) $tag);
        // IG geçerli tag karakterleri: harfler + rakamlar + alt çizgi
        $tag = preg_replace('/[^\p{L}\p{N}_]/u', '', (string) $tag);

        return (string) $tag;
    }

    /**
     * Tag, blacklist'teki herhangi bir kelimeyi içeriyor mu?
     */
    private function isBlacklisted(string $tag, array $blacklist): bool
    {
        $tagAscii = $this->stripDiacritics($tag);
        foreach ($blacklist as $bad) {
            $badAscii = $this->stripDiacritics(mb_strtolower((string) $bad, 'UTF-8'));
            if ($badAscii === '' || mb_strlen($badAscii, 'UTF-8') < 3) {
                continue;
            }
            if (str_contains($tagAscii, $badAscii)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tag, whitelist'teki en az bir kökle örtüşüyor mu? Bidirectional substring:
     * "köytereyağı" → 'koy' içinde, kabul. "tarımsal" → 'tarim' bulur, kabul.
     */
    private function isWhitelisted(string $tag, array $whitelist, array $cities): bool
    {
        $tagAscii = $this->stripDiacritics($tag);
        $combined = array_merge($whitelist, $cities);

        foreach ($combined as $allowed) {
            $allowedAscii = $this->stripDiacritics(mb_strtolower((string) $allowed, 'UTF-8'));
            if ($allowedAscii === '' || mb_strlen($allowedAscii, 'UTF-8') < 3) {
                continue;
            }
            // Bidirectional: tag ⊃ allowed VEYA allowed ⊃ tag
            if (str_contains($tagAscii, $allowedAscii) || str_contains($allowedAscii, $tagAscii)) {
                return true;
            }
        }

        return false;
    }

    private function stripDiacritics(string $s): string
    {
        // 1) Combining marks temizle (mb_strtolower 'İ' → 'i' + U+0307 üretiyor;
        //    \p{Mn} = Mark, Nonspacing → tüm combining diacritics yakalar)
        $s = preg_replace('/\p{Mn}/u', '', $s) ?? $s;

        // 2) Türkçe karakterleri ASCII'ye map et
        return strtr($s, self::DIACRITICS_MAP);
    }

    private function slugToTag(string $slug): ?string
    {
        $tag = preg_replace('/[^\p{L}\p{N}]/u', '', mb_strtolower($slug, 'UTF-8'));

        return ! empty($tag) ? (string) $tag : null;
    }

    /**
     * Blog title'ında geçen şehir adlarını bul (max 3).
     *
     * @param  list<string>  $cities
     * @return list<string>
     */
    private function detectCities(string $title, array $cities): array
    {
        $titleAscii = $this->stripDiacritics(mb_strtolower($title, 'UTF-8'));
        $found = [];

        foreach ($cities as $city) {
            $cityAscii = $this->stripDiacritics(mb_strtolower($city, 'UTF-8'));
            // Word boundary için \b — "izmir" başlı başına geçsin, "izmirli" değil
            if (preg_match('/\b' . preg_quote($cityAscii, '/') . '\b/u', $titleAscii)) {
                $found[] = $city;
                if (count($found) >= 3) {
                    break;
                }
            }
        }

        return $found;
    }
}
