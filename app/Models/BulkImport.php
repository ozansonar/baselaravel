<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Bulk Schedule (Toplu Plan) takip kaydı.
 */
final class BulkImport extends Model
{
    public const string STATUS_PENDING        = 'pending';
    public const string STATUS_PROCESSING     = 'processing';
    public const string STATUS_COMPLETED      = 'completed';
    public const string STATUS_PARTIAL_FAILED = 'partial_failed';
    public const string STATUS_FAILED         = 'failed';

    protected $fillable = [
        'created_by',
        'status',
        'total_rows',
        'processed_rows',
        'success_count',
        'fail_count',
        'errors',
        'zip_path',
        'prompt_template_id',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'errors'             => 'array',
            'created_by'         => 'integer',
            'prompt_template_id' => 'integer',
            'total_rows'         => 'integer',
            'processed_rows'     => 'integer',
            'success_count'      => 'integer',
            'fail_count'         => 'integer',
            'started_at'         => 'datetime',
            'finished_at'        => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Form-level seçilen default AI Prompt Template (varsa).
     */
    public function promptTemplate(): BelongsTo
    {
        return $this->belongsTo(AiImagePrompt::class, 'prompt_template_id');
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_PARTIAL_FAILED,
            self::STATUS_FAILED,
        ], true);
    }

    /**
     * Satır işlendiğinde sayaç güncellemesi — race-condition güvenli.
     *
     * Eloquent::increment() raw SQL UPDATE kullandığı için atomik. Errors JSON
     * güncellemesi için lockForUpdate ile satır kilitlenir (concurrent yazımda
     * birinin kaybolmaması için).
     *
     * @param array{row: int, message: string}|null $errorEntry
     */
    public function recordRowResult(bool $success, ?array $errorEntry = null): void
    {
        // Atomik counter increment'ler
        $this->increment('processed_rows');
        if ($success) {
            $this->increment('success_count');
        } else {
            $this->increment('fail_count');
        }

        // Errors JSON güncellemesi + final status — transaction altında lockForUpdate
        DB::transaction(function () use ($errorEntry): void {
            /** @var self $fresh */
            $fresh = self::query()->whereKey($this->id)->lockForUpdate()->first();
            if ($fresh === null) {
                return;
            }

            $updates = [];

            if ($errorEntry !== null) {
                $errors = $fresh->errors ?? [];
                $errors[] = $errorEntry;
                $updates['errors'] = $errors;
            }

            // Tamamlanma kontrolü
            if ($fresh->processed_rows >= $fresh->total_rows && ! $fresh->isFinished()) {
                $updates['status'] = $fresh->fail_count === 0
                    ? self::STATUS_COMPLETED
                    : ($fresh->fail_count === $fresh->total_rows
                        ? self::STATUS_FAILED
                        : self::STATUS_PARTIAL_FAILED);
                $updates['finished_at'] = now();
            }

            if ($updates !== []) {
                $fresh->update($updates);
            }
        });

        // Caller'ın in-memory state'i güncel olsun
        $this->refresh();
    }
}
