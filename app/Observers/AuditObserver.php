<?php

declare(strict_types=1);

namespace App\Observers;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Audit Observer — kritik model'lerde otomatik kayıt.
 *
 * Bind: AppServiceProvider::boot() içinde
 *   InstagramPost::observe(AuditObserver::class);
 *   Setting::observe(AuditObserver::class);
 *   ...
 */
final class AuditObserver
{
    public function created(Model $model): void
    {
        AuditLogger::log('created', $model, [], $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        AuditLogger::log('updated', $model, $model->getOriginal(), $model->getChanges());
    }

    public function deleted(Model $model): void
    {
        AuditLogger::log('deleted', $model, $model->getOriginal(), []);
    }
}
