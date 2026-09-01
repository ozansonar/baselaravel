<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\LanguageService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class MailTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'key',
        'locale',
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
     * Bir dildeki etkin şablonu bulup değişkenlerini yerine koyar.
     *
     * Tablo (key, locale) başına bir satır tutuyor. İstenen dilin satırı yoksa
     * — dil sonradan eklenmiş, satırı kapatılmış — varsayılan dilin satırına
     * düşülüyor: alıcıya yanlış dilde bir mail göndermek, hiç göndermemekten
     * iyidir; sessizce Blade karşılığına düşmek ise ikinci bir metin kaynağı
     * daha devreye sokardı.
     *
     * @param  array<string, string|null> $data Değişken adı => değer
     * @return array{subject: string, body: string}|null
     */
    public static function render(string $key, array $data = [], ?string $locale = null): ?array
    {
        $template = self::forLocale($key, $locale ?? app()->getLocale());

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

    /**
     * İstenen dildeki etkin satır, yoksa varsayılan dildeki.
     */
    private static function forLocale(string $key, string $locale): ?self
    {
        $rows = self::query()
            ->where('key', $key)
            ->where('is_active', true)
            ->whereIn('locale', array_unique([$locale, self::defaultLocale()]))
            ->get();

        return $rows->firstWhere('locale', $locale)
            ?? $rows->firstWhere('locale', self::defaultLocale());
    }

    /**
     * Sitenin varsayılan dili — şablon bulunamadığında düşülen dil.
     */
    private static function defaultLocale(): string
    {
        return app(LanguageService::class)->defaultCode();
    }
}
