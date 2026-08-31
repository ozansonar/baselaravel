<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class MailTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'key',
        'name',
        'description',
        'subject',
        'body',
        'variables',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Şablon anahtarı → ikon ve renk.
     *
     * Kart listesinde altı şablon yan yana duruyor; hepsi aynı zarf ikonunu
     * taşıdığında hangisine bakıldığı ancak başlık okunarak anlaşılıyordu.
     */
    private const KEY_VISUALS = [
        'welcome'         => ['icon' => 'bi-person-plus-fill',    'color' => 'green'],
        'verify_email'    => ['icon' => 'bi-shield-check',        'color' => 'blue'],
        'reset_password'  => ['icon' => 'bi-key-fill',            'color' => 'orange'],
        'contact_message' => ['icon' => 'bi-chat-dots-fill',      'color' => 'purple'],
        'contact_reply'   => ['icon' => 'bi-reply-fill',          'color' => 'teal'],
        'test'            => ['icon' => 'bi-wrench-adjustable',   'color' => 'muted'],
        'blog_comment_admin'    => ['icon' => 'bi-chat-left-text-fill', 'color' => 'purple'],
        'blog_comment_received' => ['icon' => 'bi-hourglass-split',     'color' => 'orange'],
        'blog_comment_approved' => ['icon' => 'bi-patch-check-fill',    'color' => 'green'],
    ];

    public function getIconAttribute(): string
    {
        return self::KEY_VISUALS[$this->key]['icon'] ?? 'bi-envelope-open-fill';
    }

    public function getColorAttribute(): string
    {
        return self::KEY_VISUALS[$this->key]['color'] ?? 'teal';
    }

    /**
     * Şablonun kullandığı değişken anahtarları.
     *
     * @return array<int, string>
     */
    public function variableKeys(): array
    {
        return array_values(array_filter(array_map(
            static fn (array $variable): string => (string) ($variable['key'] ?? ''),
            $this->variables ?? [],
        )));
    }

    /**
     * Find an active template by key and replace variables.
     *
     * @param  array<string, string|null> $data Variable key => value pairs
     * @return array{subject: string, body: string}|null
     */
    public static function render(string $key, array $data = []): ?array
    {
        $template = self::where('key', $key)
            ->where('is_active', true)
            ->first();

        if (! $template) {
            return null;
        }

        $replacements = [];
        foreach ($data as $varKey => $value) {
            $replacements['{' . $varKey . '}'] = (string) ($value ?? '');
        }

        return [
            'subject' => strtr($template->subject, $replacements),
            'body'    => strtr($template->body, $replacements),
        ];
    }
}
