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

        if (!$template) {
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
