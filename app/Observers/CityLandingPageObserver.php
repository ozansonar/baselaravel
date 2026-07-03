<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\CityLandingPage;
use App\Services\Google\IndexingNotifier;
use App\Services\RedirectService;

/**
 * Defansif observer: city_slug değiştiğinde veya kayıt silindiğinde
 * eski URL'in (/{slug}-koy-urunleri) 404 vermemesi için 301 redirect
 * oluşturur. PageObserver pattern'inin aynısı.
 *
 * Indexing API: aktif sayfa kaydedildiğinde URL_UPDATED, silindiğinde
 * URL_DELETED gönderilir (Google'a anında haber).
 */
final class CityLandingPageObserver
{
    public function __construct(
        private readonly RedirectService $redirectService,
        private readonly IndexingNotifier $indexingNotifier,
    ) {}

    public function saved(CityLandingPage $page): void
    {
        if (! $page->is_active) {
            return;
        }

        if ($page->wasRecentlyCreated || $page->wasChanged(['city_name', 'city_slug', 'title', 'meta_title', 'meta_description', 'hero_heading', 'content', 'is_active'])) {
            $this->indexingNotifier->notifyUpdate(
                url('/' . $page->city_slug . '-koy-urunleri'),
                $page,
            );
        }
    }

    public function updating(CityLandingPage $page): void
    {
        if ($page->isDirty('city_slug')) {
            $oldSlug = $page->getOriginal('city_slug');
            $this->redirectService->createAutoRedirect(
                '/' . $oldSlug . '-koy-urunleri',
                '/' . $page->city_slug . '-koy-urunleri',
            );

            $this->indexingNotifier->notifyDelete(
                url('/' . $oldSlug . '-koy-urunleri'),
                $page,
            );
        }
    }

    public function deleting(CityLandingPage $page): void
    {
        $this->indexingNotifier->notifyDelete(
            url('/' . $page->city_slug . '-koy-urunleri'),
            $page,
        );

        if (! $page->isForceDeleting()) {
            $this->redirectService->createAutoRedirect(
                '/' . $page->city_slug . '-koy-urunleri',
                '/',
            );
        }
    }
}
