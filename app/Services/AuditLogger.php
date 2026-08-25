<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Audit log üretici — model değişikliklerini ve özel olayları kaydeder.
 *
 * Kullanım:
 *  AuditLogger::log('updated', $post, $oldAttrs, $newAttrs);
 *  AuditLogger::custom('Sistem yedek aldı', context: ['size_mb' => 145]);
 */
final class AuditLogger
{
    /**
     * Sensitive (loglanmaması gereken) alanlar — password, token vs.
     *
     * @var list<string>
     */
    private const SENSITIVE_FIELDS = [
        'password', 'remember_token', 'api_token',
        'instagram_access_token', 'instagram_app_secret', 'instagram_facebook_page_token',
        'gemini_api_key', 'ai_image_api_key', 'recaptcha_secret_key',
        'google_places_api_key', 'youtube_api_key',
        'mail_password', 'telegram_bot_token',
    ];

    /**
     * Model olayı kaydet (created/updated/deleted).
     *
     * @param array<string, mixed> $oldValues
     * @param array<string, mixed> $newValues
     */
    public static function log(string $event, Model $model, array $oldValues = [], array $newValues = []): void
    {
        try {
            AuditLog::create([
                'user_id'        => Auth::id(),
                'event'          => $event,
                'auditable_type' => get_class($model),
                'auditable_id'   => $model->getKey(),
                'label'          => self::buildLabel($event, $model),
                'old_values'     => self::sanitize($oldValues),
                'new_values'     => self::sanitize($newValues),
                'ip_address'     => Request::ip(),
                'user_agent'     => substr((string) Request::header('User-Agent', ''), 0, 500),
                'url'            => substr((string) Request::fullUrl(), 0, 500),
                'created_at'     => now(),
            ]);
        } catch (\Throwable) {
            // Audit kaydı asla ana akışı bozmamalı — silently skip
        }
    }

    /**
     * Custom event — model dışı olaylar (backup, login, AI generate vs.).
     *
     * @param array<string, mixed> $context
     */
    public static function custom(string $label, array $context = [], ?int $userId = null): void
    {
        try {
            AuditLog::create([
                'user_id'        => $userId ?? Auth::id(),
                'event'          => AuditLog::EVENT_CUSTOM,
                'auditable_type' => null,
                'auditable_id'   => null,
                'label'          => substr($label, 0, 250),
                'old_values'     => null,
                'new_values'     => self::sanitize($context),
                'ip_address'     => Request::ip(),
                'user_agent'     => substr((string) Request::header('User-Agent', ''), 0, 500),
                'url'            => substr((string) Request::fullUrl(), 0, 500),
                'created_at'     => now(),
            ]);
        } catch (\Throwable) {
            // silently skip
        }
    }

    /**
     * Eski log'ları temizle (cron tarafından çağrılır).
     *
     * Saklama süresi temizliği olduğu için forceDelete kullanılır; normal
     * delete yalnızca deleted_at doldurur ve tablo süresiz büyümeye devam
     * ederdi.
     */
    public static function pruneOlderThan(int $days = 90): int
    {
        return AuditLog::withTrashed()
            ->where('created_at', '<', now()->subDays($days))
            ->forceDelete();
    }

    /**
     * Hassas alanları value'larından çıkar.
     *
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private static function sanitize(array $values): array
    {
        foreach (self::SENSITIVE_FIELDS as $field) {
            if (array_key_exists($field, $values)) {
                $values[$field] = '[REDACTED]';
            }
        }
        return $values;
    }

    private static function buildLabel(string $event, Model $model): string
    {
        $type = class_basename($model);
        $id = $model->getKey();

        $name = $model->getAttribute('name')
            ?? $model->getAttribute('title')
            ?? $model->getAttribute('topic')
            ?? $model->getAttribute('caption')
            ?? null;

        $name = $name ? mb_strimwidth((string) $name, 0, 60, '…', 'UTF-8') : null;

        $eventTr = match ($event) {
            'created' => 'oluşturuldu',
            'updated' => 'güncellendi',
            'deleted' => 'silindi',
            default   => $event,
        };

        return $name
            ? "{$type} #{$id} \"{$name}\" {$eventTr}"
            : "{$type} #{$id} {$eventTr}";
    }
}
