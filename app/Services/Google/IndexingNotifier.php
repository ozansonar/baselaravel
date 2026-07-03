<?php

declare(strict_types=1);

namespace App\Services\Google;

use App\Jobs\SubmitIndexingRequestJob;
use App\Models\IndexingRequest;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Shared helper — observer'lar bunu çağırarak Indexing API queue'una iter.
 * Feature flag kapalıysa veya Service Account JSON yoksa sessizce no-op.
 *
 * Observer'lar yerine bu sınıfa bağımlı olur → model değiştiğinde URL nasıl
 * üretileceği `notifyUpdate(url, model)` çağıran tarafın sorumluluğunda.
 */
final class IndexingNotifier
{
    public function __construct(
        private readonly IndexingApiService $indexing,
    ) {}

    public function isEnabled(): bool
    {
        if (Setting::getValue('google_indexing_enabled', '0') !== '1') {
            return false;
        }

        return $this->indexing->isEnabled();
    }

    public function notifyUpdate(string $url, ?Model $related = null): void
    {
        if (! $this->isEnabled() || $url === '') {
            return;
        }

        $this->dispatch($url, IndexingRequest::TYPE_UPDATED, $related);
    }

    public function notifyDelete(string $url, ?Model $related = null): void
    {
        if (! $this->isEnabled() || $url === '') {
            return;
        }

        $this->dispatch($url, IndexingRequest::TYPE_DELETED, $related);
    }

    private function dispatch(string $url, string $type, ?Model $related): void
    {
        try {
            $request = $this->indexing->queue(
                url: $url,
                type: $type,
                relatedType: $related !== null ? $related::class : null,
                relatedId: $related !== null ? (int) $related->getKey() : null,
            );

            SubmitIndexingRequestJob::dispatch($request->id);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
