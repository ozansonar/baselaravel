<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AiLogSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GenerateTopicsRequest;
use App\Models\Product;
use App\Models\ProductTopic;
use App\Services\AiTopicService;
use App\Services\BlogGenerationService;
use App\Services\TopicRotationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class ProductTopicController extends Controller
{
    public function __construct(
        private readonly AiTopicService $aiTopicService,
        private readonly BlogGenerationService $blogGenerationService,
        private readonly TopicRotationService $topicRotationService,
    ) {}

    /**
     * Topic Cluster yönetim sayfası — tüm ürünler ve mevcut topic'leri.
     */
    public function index(): View
    {
        $this->authorize('viewAny', \App\Models\Product::class);

        $products = Product::active()
            ->with([
                'category',
                'topics' => fn ($q) => $q->sorted(),
                'topics.blogPost:id,slug,title,blog_category_id',
                'topics.blogPost.category:id,slug',
            ])
            ->withCount('topics')
            ->orderBy('name')
            ->get();

        // ── Stats hesapla ──
        $totalTopics     = (int) ProductTopic::query()->count();
        $convertedTopics = (int) ProductTopic::query()->whereNotNull('blog_post_id')->count();
        $poolSize        = $this->topicRotationService->poolSize();
        $productsWithoutTopics = $products->filter(fn ($p) => $p->topics_count === 0)->count();

        // Cron 4 blog/gün üretiyor (routes/console.php). Topic havuzu varsa
        // her cron çalışmasında havuzdan bir topic tüketilir → 4 topic/gün.
        // Havuz boşsa fallback "rastgele ürün adı" devreye girer.
        $dailyConsumption = 4;
        $daysUntilEmpty   = $poolSize > 0 ? (int) floor($poolSize / $dailyConsumption) : 0;

        // Havuz durumu — UI uyarısı için
        $poolStatus = match (true) {
            $poolSize === 0      => 'empty',
            $poolSize < 10       => 'critical',
            $poolSize < 30       => 'warning',
            default              => 'healthy',
        };

        return view('admin.product-topics.index', [
            'products'              => $products,
            'totalTopics'           => $totalTopics,
            'convertedTopics'       => $convertedTopics,
            'poolSize'              => $poolSize,
            'productsWithoutTopics' => $productsWithoutTopics,
            'dailyConsumption'      => $dailyConsumption,
            'daysUntilEmpty'        => $daysUntilEmpty,
            'poolStatus'            => $poolStatus,
        ]);
    }

    /**
     * AI ile bir ürün için 7 topic önerisi üret. AJAX endpoint.
     */
    public function generate(GenerateTopicsRequest $request): JsonResponse
    {
        $this->authorize('create', \App\Models\Product::class);

        @set_time_limit(180);

        $product = Product::findOrFail($request->integer('product_id'));

        $result = $this->aiTopicService->generateForProduct($product);

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'topics'  => collect($result['topics'] ?? [])->map(fn (ProductTopic $t) => [
                'id'             => $t->id,
                'title'          => $t->title,
                'search_intent'  => $t->search_intent,
                'is_converted'   => $t->isConverted(),
            ])->values(),
        ]);
    }

    /**
     * Tek bir topic'i mevcut BlogGenerationService ile blog yazısına çevir.
     * Topic'in kategori_slug'ı ürün kategorisinden gelir.
     * Başarılıysa topic.blog_post_id ve converted_at güncellenir.
     */
    public function convertToBlog(ProductTopic $productTopic): JsonResponse
    {
        $this->authorize('create', \App\Models\Product::class);

        if ($productTopic->isConverted()) {
            return response()->json([
                'success' => false,
                'message' => 'Bu konu zaten blog yazısına çevrilmiş.',
            ], 422);
        }

        @set_time_limit(300);

        // BlogGenerationService default kategorisi olan 'urunlerimiz' yerine
        // ürünün kendi kategorisini öncelikli kullanmak istersek burada
        // override edebiliriz; şimdilik AiPromptSetting default'unu kullan
        // (admin AI Generate sayfasındaki davranışla aynı).
        $result = $this->blogGenerationService->generate([
            'topic'        => $productTopic->title,
            // 'city' => null → manuel üretim, cron rotation'ını bozmaz
            'city'         => null,
            // search_intent → AI'ın içerik tonunu doğru hizalaması için
            // (örn: storage başlığında recipe içerik üretmesin)
            'topic_intent' => $productTopic->search_intent,
            'force'        => true,
        ]);

        // BlogGenerationService varsayılan olarak BlogScheduled source'uyla log
        // açıyor — convertToBlog'dan gelen çağrıyı TopicCluster olarak işaretle
        // ki AI Loglar sayfasında doğru kategoride filtrelenebilsin.
        if (isset($result['log']) && $result['log']->source !== AiLogSource::TopicCluster) {
            $result['log']->update(['source' => AiLogSource::TopicCluster]);
        }

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'log_id'  => $result['log']->id ?? null,
            ], 422);
        }

        // Mark topic as converted — ortak markConverted davranışı
        // (cron path'taki BlogGenerationService de aynı service'i çağırır).
        $this->topicRotationService->markConverted($productTopic, $result['post']->id);
        $productTopic->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Blog yazısı oluşturuldu.',
            'topic'   => [
                'id'           => $productTopic->id,
                'is_converted' => true,
                'blog_post' => [
                    'id'    => $result['post']->id,
                    'title' => $result['post']->title,
                    'edit_url' => route('admin.blog-posts.edit', $result['post']->id),
                ],
            ],
        ]);
    }

    /**
     * Topic'i sil (soft delete).
     */
    public function destroy(ProductTopic $productTopic): RedirectResponse
    {
        $this->authorize('delete', \App\Models\Product::class);

        $productTopic->delete();

        return back()->with('success', 'Konu silindi.');
    }
}
