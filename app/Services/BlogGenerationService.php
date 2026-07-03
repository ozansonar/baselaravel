<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AiLogSource;
use App\Enums\AiLogStatus;
use App\Models\AiLog;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Setting;
use App\Models\User;
use App\Services\Concerns\ParsesAiJsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

final class BlogGenerationService
{
    use ParsesAiJsonResponse;
    public function __construct(
        private readonly AiService $aiService,
        private readonly BlogService $blogService,
        private readonly AiPromptSettingService $aiPromptSettingService,
        private readonly CityRotationService $cityRotationService,
        private readonly InternalLinkService $internalLinkService,
        private readonly TopicRotationService $topicRotationService,
        private readonly MediaLibraryService $mediaLibraryService,
        private readonly BlogToInstagramService $blogToInstagramService,
    ) {}

    /**
     * Generate and persist a blog post using AI.
     *
     * @param  array{category_slug?: string|null, topic?: string|null, topic_intent?: string|null, city?: string|null, force?: bool}  $options
     * @return array{success: bool, message: string, log: AiLog, post?: BlogPost}
     */
    public function generate(array $options = []): array
    {
        $setting = $this->aiPromptSettingService->get();

        if (! $setting->is_active && empty($options['force'] ?? false)) {
            return [
                'success' => false,
                'message' => 'AI içerik üretimi pasif durumda. Admin panelden aktif edin.',
                'log'     => AiLog::create([
                    'status'        => AiLogStatus::Failed,
                    'source'        => AiLogSource::BlogScheduled,
                    'error_message' => 'AI içerik üretimi pasif durumda.',
                ]),
            ];
        }

        // Resolve product/topic.
        //
        // Priority (cron path — caller hiç 'topic' geçirmedi):
        //   1. ProductTopic havuzu (Modül 4 Faz 2) — admin'in 7'şer üretip
        //      henüz blog'a çevirmediği başlıklardan rastgele birini seç.
        //   2. Havuz boşsa: AiPromptSetting.products'tan rastgele ürün adı
        //      (eski Modül 1+2 davranışı).
        //
        // Manuel path (caller 'topic' geçirdi): havuza dokunma, geleni kullan.
        $topic = trim((string) ($options['topic'] ?? ''));
        $products = $setting->products ?? [];
        $selectedTopic = null; // ProductTopic instance — havuzdan seçildiyse

        if ($topic === '') {
            $selectedTopic = $this->topicRotationService->pickNextTopic();

            if ($selectedTopic !== null) {
                // Havuzdan seçilen topic'in başlığı + intent + ürün adı geçerli.
                $topic = $selectedTopic->title;
                if (! isset($options['topic_intent']) && $selectedTopic->search_intent) {
                    $options['topic_intent'] = $selectedTopic->search_intent;
                }
            } elseif (empty($products)) {
                $log = AiLog::create([
                    'status'        => AiLogStatus::Failed,
                    'source'        => AiLogSource::BlogScheduled,
                    'error_message' => 'Ürün listesi boş. Admin panelden en az bir ürün ekleyin.',
                ]);
                Log::error('Blog generate başarısız: ürün listesi boş');

                return [
                    'success' => false,
                    'message' => 'Ürün listesi boş. Admin panelden en az bir ürün ekleyin veya konu yazın.',
                    'log'     => $log,
                ];
            } else {
                // Fallback: havuz tükendi → rastgele ürün adı.
                $topic = $products[array_rand($products)];
            }
        }

        // Resolve category
        $categorySlug = $options['category_slug'] ?? $setting->category_slug;

        // Resolve target city for this run.
        // Priority: explicit option > round-robin selector. Empty/null = generic post.
        $city = $this->resolveCity($options);

        $systemInstruction = strtr($setting->system_instruction, [
            '{product}'   => $topic,
            '{max_words}' => (string) $setting->max_words,
        ]);

        // JSON güvenlik kuralı — admin'in prompt'una dokunmadan runtime'da enjekte.
        // AI içerik içinde escape edilmemiş " kullanırsa JSON parse kırılıyor;
        // bu blok modeli alternatiflere yönlendirir.
        $systemInstruction .= $this->buildJsonSafetyBlock();

        // Şehir hedefli prompt eklentisi — round-robin ile seçilmiş şehir için
        // yerel anahtar kelime, kargo bilgisi ve CTA zorunluluğu ekler. Şehir
        // null ise (generic post slot) bu blok eklenmez.
        if ($city !== null) {
            $systemInstruction .= $this->buildCityTargetingBlock($city);
        }

        // Topic Cluster intent eklentisi (Modül 4) — convertToBlog'dan gelen
        // çağrılarda search_intent verilmişse AI'ın içerik tonunu doğru
        // hizalaması için ek talimat. Cron çağrılarında null gelir, bu blok
        // eklenmez.
        $topicIntent = isset($options['topic_intent']) && $options['topic_intent'] !== ''
            ? trim((string) $options['topic_intent'])
            : null;
        if ($topicIntent !== null) {
            $systemInstruction .= $this->buildTopicIntentBlock($topicIntent);
        }

        // Tekrar engelleme: son 30 günün başlıklarını prompt'a ekle ki model
        // benzer içerik üretmesin (cron günde 4 kez çalışıyor).
        $recentTitles = $this->getRecentTitles($topic);
        if (! empty($recentTitles)) {
            $systemInstruction .= "\n\nÖZGÜNLÜK ZORUNLULUĞU\nSon dönemde şu başlıklar yayınlandı, bunlara KESİNLİKLE benzeme — farklı bir açıdan yaklaş, farklı bir başlık kalıbı kullan:\n- " . implode("\n- ", $recentTitles);
        }

        $userPrompt = $setting->user_prompt;

        // Step 1: Log start.
        // Source: havuzdan topic seçildiyse TopicCluster (Modül 4 Faz 2),
        // aksi halde klasik BlogScheduled (Modül 2). Bu sayede AI Loglar
        // sayfasında "Topic Cluster" filtresi cron yazılarını da yakalar.
        $logSource = $selectedTopic !== null
            ? AiLogSource::TopicCluster
            : AiLogSource::BlogScheduled;

        // İki ayrı bilgi loglanıyor:
        //   - product_name      : içerik başlığı / topic (admin'in gördüğü "konu")
        //   - selected_product  : cron'un AiPromptSetting.products listesinden
        //                        veya ProductTopic ilişkisinden seçtiği ÜRÜN adı
        // TopicCluster path'inde başlık ile ürün ayrı kavramlar, bu yüzden
        // kullanıcı her ikisini de görmek istiyor.
        //
        // MediaLibrary eşleşmesi ürün adı ile birebir yapılır. Manuel üretimde
        // topic serbest metin olabileceğinden, ürün listesinden en yakın eşleşmeyi bul.
        $selectedProductName = $selectedTopic !== null && $selectedTopic->product !== null
            ? $selectedTopic->product->name
            : $this->resolveProductName($topic, $products);

        $aiLog = AiLog::create([
            'status'           => AiLogStatus::Started,
            'source'           => $logSource,
            'product_name'     => $topic,
            'selected_product' => $selectedProductName,
            'city_name'        => $city,
            'product_topic_id' => $selectedTopic?->id,
            'category_slug'    => $categorySlug,
            'prompt'           => mb_substr($systemInstruction . "\n\n---\n\n" . $userPrompt, 0, 10000, 'UTF-8'),
        ]);

        // Step 2: Call AI service (temperature 0.85 → daha yaratıcı/çeşitli içerik)
        // responseSchema → Gemini'ye sıkı JSON yapısı zorla. Bu sayede:
        //  - title/content/description alanları MUTLAKA döner
        //  - Field arası virgül atlanması imkansız (API tarafında valid JSON garanti)
        //  - Markdown fence ```json ... ``` artık eklenmez (raw object döner)
        $blogSchema = [
            'type'       => 'OBJECT',
            'properties' => [
                'title'       => ['type' => 'STRING'],
                'content'     => ['type' => 'STRING'],
                'description' => ['type' => 'STRING'],
            ],
            'required'         => ['title', 'content', 'description'],
            'propertyOrdering' => ['title', 'content', 'description'],
        ];
        $result = $this->aiService->generate($systemInstruction, $userPrompt, 0.85, $blogSchema);

        $aiLog->update([
            'api_duration_ms' => $result['duration_ms'],
            'token_count'     => $result['token_count'],
            'cost_usd'        => $result['cost_usd'] ?? null,
            'used_model'      => $result['used_model'] ?? null,
            'attempt_count'   => $result['attempt_count'] ?? null,
            'raw_response'    => $result['content'],
        ]);

        if (! $result['success']) {
            Log::error('Blog generate başarısız', ['message' => $result['message']]);
            $aiLog->update([
                'status'        => AiLogStatus::ApiFailed,
                'error_message' => $result['message'],
            ]);

            // Bildirim AiLogObserver tarafından merkezi olarak atılır (Telegram + bell).

            return ['success' => false, 'message' => $result['message'], 'log' => $aiLog->fresh()];
        }

        $aiLog->update(['status' => AiLogStatus::ApiCalled]);

        // Step 3: Parse response
        $content = $this->parseResponse($result['content']);

        if ($content === null) {
            Log::error('Blog generate JSON parse hatası', ['raw' => $result['content']]);
            $message = 'API yanıtı geçerli JSON formatında değil veya gerekli alanlar eksik.';
            $aiLog->update([
                'status'        => AiLogStatus::ParseError,
                'error_message' => $message,
            ]);

            // Bildirim AiLogObserver tarafından merkezi olarak atılır (Telegram + bell).

            return ['success' => false, 'message' => $message, 'log' => $aiLog->fresh()];
        }

        $aiLog->update(['parsed_response' => $content]);

        // Step 4: Find category
        $category = BlogCategory::where('slug', $categorySlug)->first();

        if (! $category) {
            Log::error('Blog generate: kategori bulunamadı', ['slug' => $categorySlug]);
            $message = "Blog kategorisi '{$categorySlug}' veritabanında bulunamadı.";
            $aiLog->update([
                'status'        => AiLogStatus::CategoryMissing,
                'error_message' => $message,
            ]);

            return ['success' => false, 'message' => $message, 'log' => $aiLog->fresh()];
        }

        // Step 5: Create post
        $adminUser = User::whereHas('roles', fn ($q) => $q->where('slug', 'admin'))->first();

        try {
            $post = $this->blogService->create([
                'blog_category_id' => $category->id,
                'user_id'          => $adminUser?->id ?? 1,
                'title'            => $content['title'],
                'excerpt'          => mb_substr($content['description'], 0, 500, 'UTF-8'),
                'body'             => $content['content'],
                'image'            => 'content/post-image.jpg',
                'meta_title'       => mb_substr($content['title'], 0, 70, 'UTF-8'),
                'meta_description' => mb_substr($content['description'], 0, 160, 'UTF-8'),
                'is_published'     => true,
                'published_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Blog generate: Post oluşturulamadı', ['error' => $e->getMessage()]);
            $aiLog->update([
                'status'        => AiLogStatus::PostFailed,
                'error_message' => $e->getMessage(),
                'error_details' => $e->getTraceAsString(),
            ]);

            return ['success' => false, 'message' => 'Blog yazısı oluşturulamadı: ' . $e->getMessage(), 'log' => $aiLog->fresh()];
        }

        // Step 5b: MediaLibrary'den kapak görseli seç.
        // Ürün eşleşmesi yoksa genel havuzdan, o da boşsa placeholder kalır.
        // Blog yayını ASLA bloke olmaz.
        try {
            $autoEnabled = Setting::getValue('blog_auto_cover_image', '1') === '1';

            Log::info('Step 5b: Kapak görseli seçimi başlıyor', [
                'post_id'              => $post->id,
                'auto_enabled'         => $autoEnabled,
                'selected_product'     => $selectedProductName,
                'current_image'        => $post->image,
            ]);

            if ($autoEnabled) {
                $asset = $this->mediaLibraryService->pickFor($selectedProductName);

                if ($asset !== null) {
                    $asset->markUsed();

                    $oldImage = $post->image;
                    $post->image = $asset->image_path;
                    $saved = $post->save();

                    Log::info('Step 5b: Kapak görseli GÜNCELLEME SONUCU', [
                        'post_id'    => $post->id,
                        'old_image'  => $oldImage,
                        'new_image'  => $asset->image_path,
                        'save_ok'    => $saved,
                        'asset_id'   => $asset->id,
                        'product'    => $asset->product_name ?? 'Genel',
                        'post_image_after_save' => $post->fresh()?->image,
                    ]);

                    $this->recordCoverImageDiagnostics($aiLog, [
                        'success'       => true,
                        'image_path'    => $asset->image_path,
                        'verify_passed' => true,
                        'attempt_count' => 1,
                        'reason'        => 'Kütüphaneden seçildi: ' . ($asset->product_name ?? 'Genel') . ' havuzu, asset #' . $asset->id,
                    ]);
                } else {
                    $label = $selectedProductName !== null && $selectedProductName !== ''
                        ? "'{$selectedProductName}' ürünü"
                        : 'Genel havuz';

                    Log::warning('Step 5b: Kütüphanede HİÇ aktif görsel yok — placeholder kalıyor', [
                        'post_id'          => $post->id,
                        'selected_product' => $selectedProductName,
                    ]);

                    $this->recordCoverImageDiagnostics($aiLog, [
                        'success'       => false,
                        'image_path'    => null,
                        'verify_passed' => false,
                        'attempt_count' => 0,
                        'reason'        => "Kütüphanede {$label} için aktif görsel yok — placeholder kullanılacak.",
                    ]);
                }
            } else {
                Log::info('Step 5b: Otomatik kapak görseli KAPALI', ['post_id' => $post->id]);

                $this->recordCoverImageDiagnostics($aiLog, [
                    'success'       => false,
                    'image_path'    => null,
                    'verify_passed' => false,
                    'attempt_count' => 0,
                    'reason'        => 'Otomatik kapak görseli ayarlardan kapalı.',
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Step 5b: Beklenmedik HATA', [
                'post_id' => $post->id,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            $this->recordCoverImageDiagnostics($aiLog, [
                'success'       => false,
                'image_path'    => null,
                'verify_passed' => false,
                'attempt_count' => 0,
                'reason'        => 'Exception: ' . $e->getMessage(),
            ]);
        }

        // Step 6: Success
        $aiLog->update([
            'status'       => AiLogStatus::Success,
            'blog_post_id' => $post->id,
        ]);

        // Modül 4 Faz 2 — Eğer topic havuzdan seçilmişse, bu topic'i blog'a
        // çevrilmiş olarak işaretle ki bir daha cron tarafından seçilmesin.
        // (Manuel ProductTopicController::convertToBlog akışı kendi mark
        // mantığını ayrıca yapıyor — orada zaten yapılıyor.)
        if ($selectedTopic !== null) {
            $this->topicRotationService->markConverted($selectedTopic, $post->id);
        }

        // Step 7: Otomatik Instagram + Facebook paylaşımı (Setting'lere bağlı).
        // Hata olursa blog yayını ASLA bloke olmaz — sadece log'a yazılır.
        // Servis içinde idempotency var: aynı blog için tekrar çağrılırsa skip.
        try {
            // category eager-load (caption + hashtag için BlogToInstagramService kullanır)
            $post->loadMissing('category');
            $igPost = $this->blogToInstagramService->shareBlog($post);
            if ($igPost !== null) {
                Log::info('Blog otomatik IG paylaşımı kuyruğa alındı', [
                    'blog_post_id'      => $post->id,
                    'instagram_post_id' => $igPost->id,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Blog otomatik IG paylaşımı başarısız (blog yayını etkilenmedi)', [
                'blog_post_id' => $post->id,
                'error'        => $e->getMessage(),
            ]);
        }

        Log::info('AI blog yazısı oluşturuldu', ['post_id' => $post->id, 'title' => $post->title]);

        return [
            'success' => true,
            'message' => "Blog yazısı oluşturuldu: {$post->title}",
            'log'     => $aiLog->fresh(),
            'post'    => $post,
        ];
    }

    /**
     * Cover image üretim sonucunu AiLog.parsed_response içine yansıt.
     * Teşhis amaçlı — /admin/ai-logs detay sayfasından "neden default görsel kaldı"
     * sorusuna cevap verir. Mevcut parsed_response (title/content/description) korunur,
     * altına `_cover_image` anahtarı eklenir.
     *
     * @param array{success: bool, image_path: ?string, verify_passed: bool, attempt_count: int, reason: string} $diagnostics
     */
    private function recordCoverImageDiagnostics(AiLog $aiLog, array $diagnostics): void
    {
        try {
            $aiLog->refresh();
            $parsed = $aiLog->parsed_response ?? [];
            if (! is_array($parsed)) {
                $parsed = [];
            }
            $parsed['_cover_image'] = $diagnostics;
            $aiLog->update(['parsed_response' => $parsed]);
        } catch (\Throwable $e) {
            Log::warning('AiLog cover image diagnostics kaydedilemedi', [
                'log_id' => $aiLog->id,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{title: string, content: string, description: string}|null
     */
    private function parseResponse(?string $raw): ?array
    {
        if (empty($raw)) {
            Log::warning('parseResponse: raw boş');
            return null;
        }

        // 1) Markdown fence + JSON extract
        $extracted = $this->extractJsonFromAiResponse($raw);
        if ($extracted === null) {
            Log::warning('parseResponse: extractJsonFromAiResponse null — JSON bloğu bulunamadı');
            return null;
        }

        // 2) json_decode dene
        $data = json_decode($extracted, true);
        $jsonErr = json_last_error();

        // 3) FAIL ise önce JSON syntax repair, sonra regex extract dene.
        if (! is_array($data) || $jsonErr !== JSON_ERROR_NONE) {
            $errMsg = $jsonErr !== JSON_ERROR_NONE ? json_last_error_msg() : 'is_array false';
            Log::warning('parseResponse: json_decode fail — repair denenecek', [
                'json_error' => $errMsg,
                'raw_head'   => mb_substr($extracted, 0, 200),
            ]);

            // 3a) Yaygın syntax bug'larını tamir et (eksik virgül, trailing comma)
            $repaired = $this->repairJsonSyntax($extracted);
            if ($repaired !== $extracted) {
                $repairedData = json_decode($repaired, true);
                if (is_array($repairedData) && json_last_error() === JSON_ERROR_NONE) {
                    Log::info('parseResponse: repair başarılı (syntax fix)');
                    $data = $repairedData;
                }
            }

            // 3b) Hâlâ fail ise field-by-field regex extract
            if (! is_array($data)) {
                $data = $this->extractFieldsWithRegex($extracted);
                if ($data === null) {
                    Log::warning('parseResponse: auto-repair de fail — title/content alanları regex ile bulunamadı');
                    return null;
                }
                Log::info('parseResponse: auto-repair başarılı (regex extract)', [
                    'fields' => array_keys($data),
                ]);
            }
        }

        // title ve content olmazsa yazı anlamsız — fail.
        if (! isset($data['title']) || trim((string) $data['title']) === '') {
            Log::warning('parseResponse: title alanı eksik');
            return null;
        }
        if (! isset($data['content']) || trim((string) $data['content']) === '') {
            Log::warning('parseResponse: content alanı eksik');
            return null;
        }

        // description opsiyonel: yoksa content'in ilk paragraph'ından üret.
        if (! isset($data['description']) || ! is_string($data['description']) || trim($data['description']) === '') {
            $data['description'] = $this->generateFallbackDescription((string) $data['content']);
        }

        // Title ve description: saf metin olmalı, hiçbir HTML etiketi geçirme.
        $data['title'] = trim(strip_tags((string) $data['title']));
        $data['description'] = trim(strip_tags((string) $data['description']));

        // Content: prompt'ta yasaklı olan etiketleri (a, img, h1, script vs.) temizle.
        $data['content'] = $this->sanitizeContentHtml((string) $data['content']);

        // Sanitize sonrası: ürün ve şehir linklerini enjekte et.
        $data['content'] = $this->internalLinkService->injectProductLinks($data['content']);
        $data['content'] = $this->internalLinkService->injectCityLinks($data['content']);

        return $data;
    }

    /**
     * Yaygın JSON syntax bug'larını tamir et.
     *
     * AI çıktısında en sık karşılaştığımız hatalar:
     *  1. Eksik virgül: `"content": "..."` ← virgül yok ← `"description": "..."`
     *  2. Trailing comma: `"description": "...",` ← } öncesi virgül
     *
     * Bu method bu iki bug'ı düzeltir. Düzeltilemeyen durumda raw'ı aynen döner.
     */
    private function repairJsonSyntax(string $raw): string
    {
        // 1) Eksik virgül: "value"\s*\n\s*"key": → "value",\n  "key":
        //    Bir string değeri kapanışından sonra newline + yeni quoted key geliyorsa
        //    aralarına virgül koy.
        $fixed = preg_replace(
            '/("(?:[^"\\\\]|\\\\.)*")(\s*\r?\n\s*)("[A-Za-z_][A-Za-z0-9_]*"\s*:)/',
            '$1,$2$3',
            $raw,
        );
        $fixed = is_string($fixed) ? $fixed : $raw;

        // 2) Trailing comma: ",\s*}" veya ",\s*]"
        $fixed = (string) preg_replace('/,(\s*[}\]])/', '$1', $fixed);

        return $fixed;
    }

    /**
     * JSON parse fail olduğunda metni kurtarma denemesi.
     *
     * AI bazen content içinde escape edilmemiş " kullanıyor → JSON bozulur.
     * Bu method raw string'ten title/content/description alanlarını regex ile
     * tek tek extract eder. Her alan kendi başına bağımsız parse edilir, biri
     * fail etse diğerleri kurtulabilir.
     *
     * @return ?array{title?: string, content?: string, description?: string}
     */
    private function extractFieldsWithRegex(string $raw): ?array
    {
        $out = [];

        // title: kısa, tek satır — basit greedy "title": "..." pattern
        if (preg_match('/"title"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/u', $raw, $m)) {
            $out['title'] = stripslashes($m[1]);
        }

        // description: kısa, tek satır — title ile aynı pattern
        if (preg_match('/"description"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/u', $raw, $m)) {
            $out['description'] = stripslashes($m[1]);
        }

        // content: çok satırlı HTML, içinde escape edilmemiş " olabilir.
        // Strateji: "content"\s*:\s*" başlangıcını bul, sonra son " işaretinden
        // önce kapatma ara — JSON yapısına göre, content'ten sonra ya
        // "description" geliyor (",) ya da JSON sona eriyor (}).
        if (preg_match('/"content"\s*:\s*"(.*?)"\s*(?:,\s*"(?:description|title)"|}\s*$)/su', $raw, $m)) {
            // Yakalanan içerikteki escape edilmemiş "leri korumak için içeriği
            // olduğu gibi al, sadece JSON-escape'leri çöz.
            $content = $m[1];
            // Standart JSON escape karakterleri: \" \\ \n \r \t \/ \uXXXX
            $content = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', static function ($mm) {
                return mb_convert_encoding(pack('H*', $mm[1]), 'UTF-8', 'UCS-2BE');
            }, $content) ?? $content;
            $content = strtr($content, [
                '\\"' => '"',
                '\\\\' => '\\',
                '\\n' => "\n",
                '\\r' => "\r",
                '\\t' => "\t",
                '\\/' => '/',
            ]);
            $out['content'] = $content;
        }

        if (! isset($out['title']) && ! isset($out['content'])) {
            return null;
        }

        return $out;
    }

    /**
     * AI yanıtında 'description' alanı yoksa içerikten otomatik üret.
     * İlk <p>...</p> bloğunu çıkar, tag'leri sıyır, 150 karaktere kadar kelime
     * sınırına saygılı şekilde kırp — Google snippet (~155 ch) için temiz çıktı.
     */
    private function generateFallbackDescription(string $contentHtml): string
    {
        $text = '';
        if (preg_match('/<p[^>]*>(.*?)<\/p>/su', $contentHtml, $m)) {
            $text = (string) $m[1];
        } else {
            $text = $contentHtml;
        }

        $text = trim(strip_tags($text));
        $text = (string) preg_replace('/\s+/u', ' ', $text);

        if ($text === '') {
            return '';
        }

        $max = 155;
        if (mb_strlen($text, 'UTF-8') <= $max) {
            return $text;
        }

        // Kelime ortasında kesme — son boşluğa kadar geri sar
        $cut = mb_substr($text, 0, $max, 'UTF-8');
        $lastSpace = mb_strrpos($cut, ' ', 0, 'UTF-8');
        if ($lastSpace !== false && $lastSpace > 100) {
            $cut = mb_substr($cut, 0, $lastSpace, 'UTF-8');
        }

        return rtrim($cut, " ,.;:-") . '…';
    }


    /**
     * Modelin (prompta rağmen) <a>, <img>, <h1>, <script> gibi yasaklı etiket
     * basmasına karşı güvenlik kalkanı. Sadece prompt'ta izin verilen etiketleri tutar.
     */
    private function sanitizeContentHtml(string $html): string
    {
        $allowedTags = '<h2><h3><p><strong><em><ul><li><br>';
        $stripped = strip_tags($html, $allowedTags);

        // strip_tags sadece etiketleri kaldırır, attribute'ları korumaz ama
        // izin verilen etiketler attribute'suz kullanılmalı (örn: <h2 onclick="">
        // güvenlik için tüm attribute'ları sil).
        $stripped = preg_replace('/<(\w+)[^>]*>/', '<$1>', $stripped) ?? $stripped;

        // Modelin <h1>'i basamaması için ekstra guard (strip_tags zaten kaldırır
        // ama emin olalım — bazı durumlarda <h1 class=""> hâlâ geçebiliyor).
        $stripped = preg_replace('/<\/?h1[^>]*>/i', '', $stripped) ?? $stripped;

        return trim($stripped);
    }

    /**
     * Son 30 günde aynı topic için (veya genel) yayınlanan blog başlıklarını döner.
     * Tekrar/benzer içerik üretimini engellemek için prompt'a eklenir.
     *
     * @return list<string>
     */
    private function getRecentTitles(string $topic): array
    {
        return BlogPost::query()
            ->where('published_at', '>=', Carbon::now()->subDays(30))
            ->where(function ($q) use ($topic) {
                $q->where('title', 'like', '%' . $topic . '%')
                  ->orWhere('body', 'like', '%' . $topic . '%');
            })
            ->orderByDesc('published_at')
            ->limit(10)
            ->pluck('title')
            ->all();
    }

    /**
     * Topic metnini AiPromptSetting.products listesiyle eşleştir.
     *
     * 1. Birebir eşleşme (chip'ten seçilmiş)
     * 2. Ürün adı topic içinde geçiyor (case-insensitive)
     * 3. Topic ürün adı içinde geçiyor (case-insensitive)
     * 4. Eşleşme yoksa null → MediaLibrary genel havuzdan seçer
     *
     * @param  list<string>  $products
     */
    private function resolveProductName(string $topic, array $products): ?string
    {
        if ($topic === '' || empty($products)) {
            return null;
        }

        $topicLower = mb_strtolower($topic, 'UTF-8');

        foreach ($products as $product) {
            if ($product === $topic) {
                return $product;
            }
        }

        foreach ($products as $product) {
            $productLower = mb_strtolower($product, 'UTF-8');

            if (str_contains($topicLower, $productLower)) {
                return $product;
            }
        }

        foreach ($products as $product) {
            $productLower = mb_strtolower($product, 'UTF-8');

            if (str_contains($productLower, $topicLower)) {
                return $product;
            }
        }

        return null;
    }

    /**
     * Resolve the target city for this run.
     * - If caller passed explicit `city` (string or null), respect it.
     * - Otherwise use round-robin from CityRotationService.
     */
    private function resolveCity(array $options): ?string
    {
        if (array_key_exists('city', $options)) {
            $explicit = $options['city'];
            if ($explicit === null) {
                return null;
            }
            $explicit = trim((string) $explicit);

            return $explicit === '' ? null : $explicit;
        }

        return $this->cityRotationService->nextCity();
    }

    /**
     * Topic Cluster (Modül 4) — kullanıcının istediği "search intent"i
     * AI'a bildirir, böylece "Köy Tereyağı Nasıl Saklanır?" başlığına
     * kullanım tarifi (recipe) yerine doğru saklama rehberi (storage)
     * içeriği yazılır.
     *
     * AI'nın content alanı içinde escape edilmemiş çift tırnak (") kullanması
     * JSON parse'ı kırıyor. Bu blok modeli alternatif vurgu yöntemlerine yönlendirir.
     */
    private function buildJsonSafetyBlock(): string
    {
        return "\n\nÇIKTI FORMATI — KESİN KURALLAR (HER BİRİNE UYULMASI ZORUNLU)\n"
            . "Yanıtın SADECE ve SADECE şu yapıda geçerli JSON objesi olacak:\n"
            . "{ \"title\": \"...\", \"content\": \"...\", \"description\": \"...\" }\n\n"
            . "ZORUNLU JSON KURALLARI:\n"
            . "- Yanıtın TAMAMI tek bir JSON objesi olacak — başında/sonunda açıklama, selamlama, markdown fence (```) YOK.\n"
            . "- 3 alan ZORUNLU: title, content, description. Hiçbiri eksik ya da boş olamaz.\n"
            . "- Alanlar bu sırayla: önce title, sonra content, sonra description.\n"
            . "- Her alan virgülle ayrılacak — son alan hariç. ARA VİRGÜLLERİ ATLAMA.\n"
            . "- Trailing comma (kapanış } öncesi virgül) KULLANMA.\n"
            . "- Yorum satırı (// veya /* */) YOK.\n\n"
            . "İÇERİK İÇİNDEKİ KARAKTERLER:\n"
            . "- title/content/description değerlerinin içinde ASLA düz çift tırnak (\") kullanma. Vurgu için <strong>...</strong>, alıntı için tek tırnak (') veya Türkçe « » kullan.\n"
            . "- Backslash (\\) kullanma. URL'lerde / kullan.\n"
            . "- Satır sonu için içeride \\n yazma; content HTML olduğu için <br> ya da paragraf <p> kullan.\n"
            . "- Bu kurallar JSON parse'ını korumak için KESİNDİR ve geçersiz yanıt KABUL EDİLMEZ.\n";
    }

    /**
     * @param  string  $intent  ProductTopic.search_intent değerlerinden biri
     */
    private function buildTopicIntentBlock(string $intent): string
    {
        $guides = [
            'how_to'       => "İÇERİK AÇISI: Nasıl yapılır rehberi.\n- Adım adım üretim/yapım süreci anlat.\n- Pratik tarif/talimat ton; sırayla numaralı veya işaretli liste kullanabilirsin.\n- Köy/geleneksel yöntemleri ön plana çıkar.",
            'comparison'   => "İÇERİK AÇISI: Karşılaştırma.\n- Köy/doğal vs market/endüstriyel ürün karşılaştırması.\n- 5-7 somut fark maddesi; tablo/liste formatı uygun.\n- Köy üretiminin avantajlarını objektif vurgula (yalan/abartı YOK).",
            'storage'      => "İÇERİK AÇISI: Saklama rehberi.\n- Buzdolabı, buzluk, kiler şartları; raf ömrü, sıcaklık önerileri.\n- Bozulma belirtileri ve önleme.\n- Pratik ipucu listesi.",
            'purchase'     => "İÇERİK AÇISI: Online sipariş/satın alma rehberi.\n- Çorum'dan kargo süreci, soğuk zincir teslimat detayı.\n- Sipariş adımları, ödeme, iade.\n- 'Sipariş ver' CTA'sı sonda olsun.",
            'recipe'       => "İÇERİK AÇISI: Tarif / kullanım önerileri.\n- 3-5 farklı tarif/yemek önerisi (kahvaltı, ana yemek, atıştırmalık).\n- Her tarif için kısa malzeme + adım listesi.\n- Pratik mutfak ipuçları.",
            'health'       => "İÇERİK AÇISI: Sağlık faydaları (BİLİMSEL, abartısız).\n- Besin değerleri, vitamin/mineral içeriği.\n- 'Tedavi eder' gibi tıbbi iddia YASAK; 'destekler', 'katkı sağlar' gibi temkinli dil kullan.\n- 5-7 somut sağlık faydası.",
            'buying_guide' => "İÇERİK AÇISI: Alırken dikkat edilecekler.\n- Kalite anlama yöntemleri (renk, koku, kıvam, etiket).\n- Sahte/karışık ürün belirtileri.\n- Alış kontrol listesi, kalite işaretleri.",
        ];

        $guide = $guides[$intent] ?? "İÇERİK AÇISI: {$intent}";

        return "\n\nTOPIC CLUSTER YÖNERGESİ\n{$guide}\n- Başlığı değiştirme; verilen başlığa SADIK kal.";
    }

    /**
     * Build the city-targeting prompt block injected into the system instruction
     * when this run is allocated to a specific city. Mirrors the spec literally.
     */
    private function buildCityTargetingBlock(string $city): string
    {
        return "\n\nHEDEF ŞEHİR: {$city}\n"
            . "Bu yazıda \"{$city}\" şehrini hedefle:\n"
            . "- \"{$city} köy ürünleri\", \"{$city} doğal süt\", \"{$city} köy tereyağı\" gibi yerel anahtar kelimeleri doğal akışla kullan.\n"
            . "- Yazıda en az 1 kez {$city}'a kargo/teslimat bilgisi (Çorum'dan {$city}'a soğuk zincir kargo, 1-2 iş günü teslimat) geçsin.\n"
            . "- Yazının sonunda \"{$city}'dan sipariş verin\" ya da benzeri bir CTA cümlesi olsun.\n"
            . "- Title'da {$city} şehir adı geçsin (örn: \"{$city} İçin Doğal Köy Tereyağı Rehberi\").\n"
            . "- Meta description'da da {$city} kelimesi mutlaka geçsin.\n"
            . "- Şehri suni yerleştirme; içerik akışında doğal hissedilsin.";
    }

    /**
     * Var olan bir AiLog kaydını (genellikle ParseError statüsünde) yeniden işle:
     * raw_response'tan parse et, başarılıysa BlogPost oluştur, log'u Success'e güncelle.
     *
     * Use case: AI 'description' alanını atlamış → eski kod parse fail demişti;
     * description fallback eklendikten sonra aynı raw_response artık geçerli.
     *
     * @return array{success: bool, message: string, log: AiLog, post?: BlogPost}
     */
    public function retryFromLog(AiLog $log): array
    {
        if (empty($log->raw_response)) {
            return [
                'success' => false,
                'message' => 'Bu log\'da raw_response yok — yeniden işlenemez.',
                'log'     => $log,
            ];
        }

        // 1) Parse (yeni description fallback'i sayesinde eskiden başarısız olan
        //    yanıtlar şimdi geçerli olabilir).
        $content = $this->parseResponse($log->raw_response);

        if ($content === null) {
            $message = 'Raw response yeniden parse edilemedi (title/content eksik olabilir).';
            $log->update([
                'status'        => AiLogStatus::ParseError,
                'error_message' => $message,
            ]);
            return ['success' => false, 'message' => $message, 'log' => $log->fresh()];
        }

        // 2) Kategori — log'dan al, yoksa AI ayarlarındaki default, o da yoksa ilk aktif kategori.
        $categorySlug = $log->category_slug
            ?? $this->aiPromptSettingService->get()->category_slug
            ?? optional(BlogCategory::query()->orderBy('id')->first())->slug;

        $category = $categorySlug
            ? BlogCategory::where('slug', $categorySlug)->first()
            : BlogCategory::query()->orderBy('id')->first();

        if ($category === null) {
            $message = 'Yayınlanacak blog kategorisi bulunamadı.';
            $log->update([
                'status'        => AiLogStatus::CategoryMissing,
                'error_message' => $message,
            ]);
            return ['success' => false, 'message' => $message, 'log' => $log->fresh()];
        }

        // 3) Post oluştur.
        $adminUser = User::whereHas('roles', fn ($q) => $q->where('slug', 'admin'))->first();

        try {
            $post = $this->blogService->create([
                'blog_category_id' => $category->id,
                'user_id'          => $adminUser?->id ?? 1,
                'title'            => $content['title'],
                'excerpt'          => mb_substr($content['description'], 0, 500, 'UTF-8'),
                'body'             => $content['content'],
                'image'            => 'content/post-image.jpg',
                'meta_title'       => mb_substr($content['title'], 0, 70, 'UTF-8'),
                'meta_description' => mb_substr($content['description'], 0, 160, 'UTF-8'),
                'is_published'     => true,
                'published_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Blog retry: Post oluşturulamadı', ['log_id' => $log->id, 'error' => $e->getMessage()]);
            $log->update([
                'status'        => AiLogStatus::PostFailed,
                'error_message' => $e->getMessage(),
                'error_details' => $e->getTraceAsString(),
            ]);
            return [
                'success' => false,
                'message' => 'Blog yazısı oluşturulamadı: ' . $e->getMessage(),
                'log'     => $log->fresh(),
            ];
        }

        // 4) Kapak görseli — MediaLibrary'den konuya uygun seç (generate Step 5b)
        try {
            $autoEnabled = Setting::getValue('blog_auto_cover_image', '1') === '1';
            if ($autoEnabled) {
                $productHint = $log->selected_product ?: $log->product_name;
                $asset = $this->mediaLibraryService->pickFor($productHint);
                if ($asset !== null) {
                    $asset->markUsed();
                    $post->image = $asset->image_path;
                    $post->save();
                    Log::info('Retry Step 5b: Kapak görseli atandı', [
                        'post_id'    => $post->id,
                        'asset_id'   => $asset->id,
                        'image_path' => $asset->image_path,
                        'product'    => $asset->product_name ?? 'Genel',
                    ]);
                    $this->recordCoverImageDiagnostics($log, [
                        'success'       => true,
                        'image_path'    => $asset->image_path,
                        'verify_passed' => true,
                        'attempt_count' => 1,
                        'reason'        => 'Retry: kütüphaneden seçildi (asset #' . $asset->id . ')',
                    ]);
                } else {
                    Log::warning('Retry Step 5b: Kütüphanede uygun görsel yok — placeholder kalıyor', [
                        'post_id' => $post->id,
                        'hint'    => $productHint,
                    ]);
                    $this->recordCoverImageDiagnostics($log, [
                        'success'       => false,
                        'image_path'    => null,
                        'verify_passed' => false,
                        'attempt_count' => 0,
                        'reason'        => 'Retry: kütüphanede uygun görsel yok',
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // Kapak başarısız olsa bile post yayınlanmış durumda — bloke etme, sadece log.
            Log::warning('Retry Step 5b: Kapak görseli exception (post etkilenmedi)', [
                'post_id' => $post->id,
                'error'   => $e->getMessage(),
            ]);
        }

        // 5) Log'u Success'e güncelle (IG paylaşımdan ÖNCE, böylece IG fail etse
        //    bile log Success kalır — IG idempotent zaten).
        $log->update([
            'status'          => AiLogStatus::Success,
            'parsed_response' => $content,
            'blog_post_id'    => $post->id,
            'error_message'   => null,
            'error_details'   => null,
        ]);

        // 6) Otomatik IG + FB paylaşımı (generate Step 7). Hata olursa blog
        //    yayını ASLA bloke olmaz — idempotency'i servis kendi içinde sağlar.
        try {
            $post->loadMissing('category');
            $igPost = $this->blogToInstagramService->shareBlog($post);
            if ($igPost !== null) {
                Log::info('Retry Step 7: IG paylaşımı kuyruğa alındı', [
                    'log_id'            => $log->id,
                    'blog_post_id'      => $post->id,
                    'instagram_post_id' => $igPost->id,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Retry Step 7: IG paylaşımı başarısız (blog yayını etkilenmedi)', [
                'log_id'       => $log->id,
                'blog_post_id' => $post->id,
                'error'        => $e->getMessage(),
            ]);
        }

        Log::info('AiLog retry başarılı', ['log_id' => $log->id, 'post_id' => $post->id]);

        return [
            'success' => true,
            'message' => 'Yazı oluşturuldu: ' . $post->title,
            'log'     => $log->fresh(),
            'post'    => $post,
        ];
    }
}
