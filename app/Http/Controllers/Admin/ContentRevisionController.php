<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\ContentRevision;
use App\Models\Page;
use App\Services\ContentRevisionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RuntimeException;

/**
 * İçeriğin sürüm geçmişi.
 *
 * Sayfa ve blog yazısı için tek denetleyici: iki ekranın yaptığı iş aynı ve
 * ayrı yazılsalardı biri ötekinden sapardı. Hangi türle çalışıldığı
 * {@see self::TARGETS} haritasından geliyor; adres satırından gelen değer
 * doğrudan sınıf adına çevrilmiyor.
 *
 * Ekran **dil başına** çalışıyor: bu projede her dil ayrı bir satır ve her
 * satırın kendi geçmişi var. TR'yi geri almak EN'i etkilemiyor — iki dili iki
 * ayrı kişi düzenlediğinde biri ötekinin işini silmesin.
 */
final class ContentRevisionController extends Controller
{
    /**
     * Adres satırındaki tür => [model, listeye dönüş rotası, düzenleme rotası, başlık].
     *
     * @var array<string, array{model: class-string<Model>, index: string, edit: string, label: string}>
     */
    private const TARGETS = [
        'sayfa' => [
            'model' => Page::class,
            'index' => 'admin.pages.index',
            'edit'  => 'admin.pages.edit',
            'label' => 'Sayfalar',
        ],
        'blog' => [
            'model' => BlogPost::class,
            'index' => 'admin.blog-posts.index',
            'edit'  => 'admin.blog-posts.edit',
            'label' => 'Blog Yazıları',
        ],
    ];

    public function __construct(
        private readonly ContentRevisionService $revisions,
    ) {}

    /**
     * GET /admin/surumler/{type}/{id}
     */
    public function index(string $type, int $id): View
    {
        [$target, $meta] = $this->resolve($type, $id);

        $this->authorize('update', $target);

        $revisions = $target->revisions()->with('author')->get();

        return view('admin.revisions.index', [
            'target'    => $target,
            'type'      => $type,
            'meta'      => $meta,
            'revisions' => $revisions,
            'fields'    => $this->revisions->fieldsFor($target),
            'keep'      => $this->revisions->keep(),
        ]);
    }

    /**
     * POST /admin/surumler/{type}/{id}/{revision}/geri-yukle
     */
    public function restore(string $type, int $id, ContentRevision $revision): RedirectResponse
    {
        [$target] = $this->resolve($type, $id);

        $this->authorize('update', $target);

        try {
            $this->revisions->restore($revision, $target);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.revisions.index', ['type' => $type, 'id' => $id])
            ->with('success', 'Seçilen sürüm geri yüklendi. Bir önceki hâl listenin başında duruyor.');
    }

    /**
     * Adres satırındaki türü ve kimliği modele çevirir.
     *
     * @return array{0: Model, 1: array{model: class-string<Model>, index: string, edit: string, label: string}}
     */
    private function resolve(string $type, int $id): array
    {
        $meta = self::TARGETS[$type] ?? null;

        if ($meta === null) {
            abort(404);
        }

        /** @var Model $target */
        $target = $meta['model']::query()->findOrFail($id);

        return [$target, $meta];
    }
}
