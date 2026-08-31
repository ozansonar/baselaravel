<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ön yüz formlarında yanlış karakter en baştan engellenmeli.
 *
 * Ad alanına rakam, telefon alanına harf yazılabiliyordu: kural yoktu, maske
 * de yoktu. Yönetim panelinde ikisi de vardı; ön yüz geride kalmıştı.
 *
 * Maske ile kural bir garantinin iki yarısı, birbirinin alternatifi değil:
 * maske yanlış karakterin yazılmasını engelliyor, kural gönderimde denetliyor,
 * sunucu da son sözü söylüyor. Maske tek başına yetmez (JS kapalı olabilir),
 * kural tek başına da yetmez (kullanıcı hatayı ancak gönderince görür).
 */
final class FrontFormInputRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        app(\App\Services\LanguageService::class)->clearCache();
    }

    /**
     * Ziyaretçinin doldurduğu ad ve telefon alanları.
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function fields(): array
    {
        return [
            'iletişim / ad'     => ['contact.blade.php', 'name', 'custom[letters]', 'letters'],
            'iletişim / telefon' => ['contact.blade.php', 'phone', 'custom[phone]', 'phone'],
            'kayıt / ad'        => ['auth/register.blade.php', 'first_name', 'custom[letters]', 'letters'],
            'kayıt / soyad'     => ['auth/register.blade.php', 'last_name', 'custom[letters]', 'letters'],
            'kayıt / telefon'   => ['auth/register.blade.php', 'phone', 'custom[phone]', 'phone'],
            'profil / ad'       => ['account/profile.blade.php', 'first_name', 'custom[letters]', 'letters'],
            'profil / soyad'    => ['account/profile.blade.php', 'last_name', 'custom[letters]', 'letters'],
            'profil / telefon'  => ['account/profile.blade.php', 'phone', 'custom[phone]', 'phone'],
            'yorum / ad'        => ['partials/blog-comments.blade.php', 'name', 'custom[letters]', 'letters'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('fields')]
    public function test_the_field_carries_both_the_rule_and_the_mask(
        string $view,
        string $field,
        string $rule,
        string $mask,
    ): void {
        $tag = $this->inputTag($view, $field);

        $this->assertNotNull($tag, "{$view} içinde {$field} alanı yok");
        $this->assertStringContainsString($rule, $tag, "{$view}/{$field} → kural eksik");
        $this->assertStringContainsString('data-fv-mask="' . $mask . '"', $tag, "{$view}/{$field} → maske eksik");
    }

    // ── Maskeler ──

    /**
     * Maskeler istemci tarafında tanımlı olmalı, yoksa nitelik hiçbir şey
     * yapmaz — alan sessizce korumasız kalır.
     */
    public function test_the_front_knows_every_mask_the_forms_ask_for(): void
    {
        $js = (string) file_get_contents(public_path('js/form-validation.js'));

        foreach (['letters', 'digits', 'decimal', 'phone'] as $mask) {
            $this->assertMatchesRegularExpression(
                '/\b' . $mask . ':\s*function/',
                $js,
                "{$mask} maskesi ön yüzde tanımlı değil",
            );
        }
    }

    /**
     * Telefon maskesi kuraldan katı olmamalı.
     *
     * "digits" seçilseydi maske artı işaretini ve boşlukları silerdi; oysa hem
     * istemci kuralı hem sunucu "+90 555 111 22 33" biçimini kabul ediyor.
     * Kullanıcı geçerli bir numarayı yazamazdı.
     */
    public function test_the_phone_mask_lets_through_what_the_rule_accepts(): void
    {
        $js = (string) file_get_contents(public_path('js/form-validation.js'));

        preg_match('/phone:\s*function\s*\([^)]*\)\s*\{(.*?)\}/s', $js, $m);

        $this->assertNotEmpty($m[1] ?? '', 'Telefon maskesi bulunamadı');

        foreach (['+', '(', ')', '-'] as $karakter) {
            $this->assertStringContainsString(
                $karakter === '+' ? '+' : $karakter,
                $m[1],
                "Telefon maskesi {$karakter} işaretini geçirmiyor",
            );
        }
    }

    // ── Sunucu son sözü söylemeli ──

    public function test_a_name_with_digits_is_refused_by_the_server(): void
    {
        $this->post(route('contact.store', ['locale' => 'tr']), [
            'name' => 'Ahmet 123', 'email' => 'a@ornek.com',
            'subject' => 'Konu', 'message' => 'Yeterince uzun bir deneme mesaji.',
        ])->assertSessionHasErrors('name');
    }

    public function test_a_phone_with_letters_is_refused_by_the_server(): void
    {
        $this->post(route('contact.store', ['locale' => 'tr']), [
            'name' => 'Ahmet Yilmaz', 'email' => 'a@ornek.com', 'phone' => 'abc555',
            'subject' => 'Konu', 'message' => 'Yeterince uzun bir deneme mesaji.',
        ])->assertSessionHasErrors('phone');
    }

    /** Maskenin geçirdiği bir numarayı sunucu reddetmemeli. */
    public function test_the_server_accepts_what_the_mask_lets_through(): void
    {
        $this->post(route('contact.store', ['locale' => 'tr']), [
            'name' => 'Ahmet Yilmaz', 'email' => 'a@ornek.com', 'phone' => '+90 (555) 111-22.33',
            'subject' => 'Konu', 'message' => 'Yeterince uzun bir deneme mesaji.',
        ])->assertSessionHasNoErrors();
    }

    public function test_a_comment_name_with_digits_is_refused(): void
    {
        $kategori = \App\Models\BlogCategory::create([
            'locale' => 'tr', 'name' => 'Genel', 'slug' => 'genel', 'is_active' => true,
        ]);

        $yazi = \App\Models\BlogPost::create([
            'locale' => 'tr', 'blog_category_id' => $kategori->id,
            'title' => 'Yazı', 'slug' => 'yazi', 'body' => 'Gövde',
            'status' => 'published', 'published_at' => now()->subDay(),
        ]);

        $this->postJson(route('blog-comments.store', ['locale' => 'tr']), [
            'blog_post_id' => $yazi->id, 'name' => 'Ahmet 123',
            'email' => 'a@ornek.com', 'body' => 'Bir deneme yorumu.',
        ])->assertStatus(422)->assertJsonValidationErrors('name');
    }

    /**
     * Bir alan doldurulabiliyorsa ya kuralı olmalı ya da bilerek dışarıda
     * bırakıldığını söyleyen işareti.
     */
    public function test_no_visible_field_is_left_without_a_rule(): void
    {
        $kuralsiz = [];

        foreach ($this->frontFormViews() as $view) {
            $source = (string) file_get_contents(resource_path('views/' . $view));

            foreach ($this->inputTags($source) as [$line, $tag]) {
                if (preg_match('/name="([^"]+)"/', $tag, $m) !== 1) {
                    continue;
                }

                // Gizli alanlar ve çerçevenin kendi alanları kullanıcı girdisi değil.
                if (str_contains($tag, 'type="hidden"') || in_array($m[1], ['_token', '_method'], true)) {
                    continue;
                }

                if (! str_contains($tag, 'data-validation-engine') && ! str_contains($tag, 'data-fv-ignore')) {
                    $kuralsiz[] = "{$view}:{$line}  {$m[1]}";
                }
            }
        }

        sort($kuralsiz);

        $this->assertSame(
            [],
            $kuralsiz,
            "Kuralsız alan — data-validation-engine ya da data-fv-ignore ekleyin:\n  " . implode("\n  ", $kuralsiz),
        );
    }

    // ── Yardımcılar ──

    /**
     * @return list<string>
     */
    private function frontFormViews(): array
    {
        return [
            'contact.blade.php',
            'auth/register.blade.php',
            'auth/login.blade.php',
            'auth/forgot-password.blade.php',
            'auth/reset-password.blade.php',
            'account/profile.blade.php',
            'partials/blog-comments.blade.php',
            'partials/newsletter-form.blade.php',
            'blog/index.blade.php',
            'search.blade.php',
        ];
    }

    private function inputTag(string $view, string $field): ?string
    {
        $source = (string) file_get_contents(resource_path('views/' . $view));

        foreach ($this->inputTags($source) as [, $tag]) {
            if (preg_match('/name="' . preg_quote($field, '/') . '"/', $tag) === 1) {
                return $tag;
            }
        }

        return null;
    }

    /**
     * Blade ifadeleri maskeleniyor; maskelenmezse ok işaretinin ">"si etiketi
     * erken kapatıyor ve nitelikler görünmüyor. Uzunluk korunuyor ki satır
     * numarası kaymasın.
     *
     * @return list<array{0: int, 1: string}>
     */
    private function inputTags(string $source): array
    {
        $blank = static fn (array $m): string => (string) preg_replace('/[^\n]/', ' ', $m[0]);
        $masked = $source;

        foreach (['/\{\{--.*?--\}\}/s', '/\{\{.*?\}\}/s', '/\{!!.*?!!\}/s'] as $pattern) {
            $masked = (string) preg_replace_callback($pattern, $blank, $masked);
        }

        $masked = str_replace('->', '  ', $masked);

        preg_match_all('/<(?:input|textarea|select)\b[^>]*?>/s', $masked, $matches, PREG_OFFSET_CAPTURE);

        $tags = [];

        foreach ($matches[0] as [$tag, $offset]) {
            $tags[] = [
                substr_count(substr($source, 0, (int) $offset), "\n") + 1,
                substr($source, (int) $offset, strlen($tag)),
            ];
        }

        return $tags;
    }
}
