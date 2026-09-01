<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SeoLevel;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\Role;
use App\Models\User;
use App\Services\Seo\SeoAuditor;
use App\Support\Seo\BodyDocument;
use App\Support\Seo\SeoSubject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * SEO denetleyici.
 *
 * Her kural için iki sınav var: tetiklendiği durum ve tetiklenmediği durum.
 * İkincisi olmadan bir kural "her zaman uyar" hâline gelebilir ve kimse fark
 * etmez — yanlış pozitif, denetimin kapatılmasının en sık sebebi.
 *
 * Sınavlar bulgu **koduna** bakıyor, mesajına değil: metinler çeviri
 * dosyasında ve değiştirilebilir olmalı; testin onlara bağlanması metni
 * dokunulmaz kılardı.
 */
class SeoAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
        $this->seed(\Database\Seeders\LanguageSeeder::class);

        BodyDocument::flush();
    }

    // ── Meta başlık ──

    public function test_a_missing_meta_title_is_reported(): void
    {
        $this->assertHasIssue('meta.title.missing', $this->subject(metaTitle: null));
    }

    public function test_a_filled_meta_title_is_not_reported(): void
    {
        $this->assertNoIssue('meta.title.missing', $this->subject());
    }

    public function test_a_meta_title_that_copies_the_page_title_is_reported(): void
    {
        $subject = $this->subject(title: 'Kurumsal Sitede İçerik Yönetimi', metaTitle: 'Kurumsal Sitede İçerik Yönetimi');

        $this->assertHasIssue('meta.title.duplicate', $subject);
    }

    public function test_a_long_meta_title_is_reported(): void
    {
        $this->assertHasIssue('meta.title.length', $this->subject(metaTitle: str_repeat('a', 90)));
    }

    public function test_a_short_meta_title_is_reported(): void
    {
        $this->assertHasIssue('meta.title.length', $this->subject(metaTitle: 'Kısa'));
    }

    public function test_a_meta_title_within_the_limits_is_not_reported(): void
    {
        $this->assertNoIssue('meta.title.length', $this->subject());
    }

    /**
     * Meta boşken uzunluk uyarısı sayfa başlığını ölçüyor; mesaj bunu
     * söylemezse panel iki çelişkili uyarıyı yan yana gösteriyor.
     */
    public function test_the_length_warning_names_the_title_it_measured(): void
    {
        $report = app(SeoAuditor::class)->audit($this->subject(title: 'Kısa', metaTitle: null));

        $issue = $this->issue($report->issues, 'meta.title.length');

        $this->assertNotNull($issue);
        $this->assertStringContainsString(__('seo.checks.meta_title.too_short_fallback', [
            'length' => 4,
            'min'    => (int) config('seo.title.min'),
        ]), $issue->message);
    }

    // ── Meta açıklama ──

    public function test_a_missing_meta_description_is_reported(): void
    {
        $this->assertHasIssue('meta.desc.missing', $this->subject(metaDescription: null));
    }

    public function test_a_meta_description_within_the_limits_is_not_reported(): void
    {
        $this->assertNoIssue('meta.desc.missing', $this->subject());
        $this->assertNoIssue('meta.desc.length', $this->subject());
    }

    public function test_a_long_meta_description_is_reported(): void
    {
        $this->assertHasIssue('meta.desc.length', $this->subject(metaDescription: str_repeat('a', 200)));
    }

    // ── Başlık yapısı ──

    public function test_a_second_h1_in_the_body_is_an_error(): void
    {
        $subject = $this->subject(body: '<h1>Fazladan</h1><p>Gövde.</p>');

        $this->assertHasIssue('heading.h1.multiple', $subject, SeoLevel::Error);
    }

    public function test_a_body_that_starts_at_h2_is_not_reported(): void
    {
        $subject = $this->subject(body: '<h2>Bölüm</h2><h3>Alt</h3><p>Gövde.</p>');

        $this->assertNoIssue('heading.h1.multiple', $subject);
        $this->assertNoIssue('heading.skipped', $subject);
    }

    public function test_a_skipped_heading_level_is_reported(): void
    {
        $this->assertHasIssue('heading.skipped', $this->subject(body: '<h2>Bölüm</h2><h4>Atlanmış</h4>'));
    }

    // ── Görseller ──

    public function test_an_image_without_alt_is_an_error(): void
    {
        $subject = $this->subject(body: '<p>Metin.</p><img src="/uploads/a.webp">');

        $this->assertHasIssue('image.alt.missing', $subject, SeoLevel::Error);
    }

    /**
     * `alt=""` bir eksik değil, bir karar: "bu görsel süs, atla".
     */
    public function test_a_deliberately_empty_alt_is_not_reported(): void
    {
        $subject = $this->subject(body: '<p>Metin.</p><img src="/uploads/a.webp" alt="">');

        $this->assertNoIssue('image.alt.missing', $subject);
    }

    public function test_a_missing_cover_image_is_reported(): void
    {
        $this->assertHasIssue('image.cover.missing', $this->subject(coverImage: null));
    }

    public function test_a_present_cover_image_is_not_reported(): void
    {
        $this->assertNoIssue('image.cover.missing', $this->subject());
    }

    // ── Bağlantı metni ──

    public function test_a_generic_link_text_is_reported(): void
    {
        $subject = $this->subject(body: '<p><a href="/tr/hakkimizda">buraya tıklayın</a></p>');

        $this->assertHasIssue('link.text.generic', $subject);
    }

    public function test_a_descriptive_link_text_is_not_reported(): void
    {
        $subject = $this->subject(body: '<p><a href="/tr/hakkimizda">Hakkımızda sayfası</a></p>');

        $this->assertNoIssue('link.text.generic', $subject);
    }

    public function test_a_link_with_no_text_is_reported(): void
    {
        $subject = $this->subject(body: '<p><a href="/tr/hakkimizda"></a></p>');

        $this->assertHasIssue('link.text.empty', $subject);
    }

    /**
     * Metin yerine görsel taşıyan bağlantıda alt metni bağlantı metni sayılıyor
     * — ekran okuyucu da öyle okuyor.
     */
    public function test_a_link_wrapping_an_image_with_alt_is_not_reported(): void
    {
        $subject = $this->subject(
            body: '<p><a href="/tr/hakkimizda"><img src="/uploads/a.webp" alt="Hakkımızda"></a></p>',
        );

        $this->assertNoIssue('link.text.empty', $subject);
    }

    // ── İç bağlantılar ──

    public function test_a_link_to_a_missing_page_is_an_error(): void
    {
        $subject = $this->subject(body: '<p><a href="/tr/hicbir-yerde-yok">Bağlantı</a></p>');

        $this->assertHasIssue('link.internal.broken', $subject, SeoLevel::Error);
    }

    public function test_a_link_to_a_real_page_is_not_reported(): void
    {
        $page = Page::factory()->create(['locale' => 'tr', 'slug' => 'gercek-sayfa']);

        $subject = $this->subject(body: '<p><a href="/tr/' . $page->slug . '">Gerçek sayfa</a></p>');

        $this->assertNoIssue('link.internal.broken', $subject);
    }

    public function test_an_external_link_is_never_checked(): void
    {
        $subject = $this->subject(body: '<p><a href="https://baska-site.test/olmayan">Dış kaynak</a></p>');

        $this->assertNoIssue('link.internal.broken', $subject);
    }

    public function test_anchors_and_mail_links_are_never_checked(): void
    {
        $subject = $this->subject(
            body: '<p><a href="#bolum">Bölüm</a> <a href="mailto:a@b.test">E-posta</a> <a href="tel:+90">Telefon</a></p>',
        );

        $this->assertNoIssue('link.internal.broken', $subject);
    }

    /**
     * Yönlendirme tanımlıysa bağlantı kırık değil: ziyaretçi bir yere gidiyor.
     */
    public function test_a_link_covered_by_a_redirect_is_not_reported(): void
    {
        Redirect::query()->create([
            'old_url'     => '/tr/eski-adres',
            'new_url'     => '/tr/yeni-adres',
            'status_code' => 301,
            'is_active'   => true,
        ]);

        $subject = $this->subject(body: '<p><a href="/tr/eski-adres">Eski adres</a></p>');

        $this->assertNoIssue('link.internal.broken', $subject);
    }

    /**
     * Yüz bağlantılı bir gövde yüz sorgu açmamalı.
     */
    public function test_checking_links_does_not_query_once_per_link(): void
    {
        Page::factory()->create(['locale' => 'tr', 'slug' => 'hedef-sayfa']);

        $auditor = app(SeoAuditor::class);

        $bir = '<p><a href="/tr/hedef-sayfa">Bir</a></p>';
        $cok = str_repeat('<p><a href="/tr/hedef-sayfa">Bir</a></p>', 40);

        // Isıtma: ilk denetim adres haritasını ve ayarları önbelleğe alıyor.
        $auditor->audit($this->subject(body: $bir));

        $ilk = $this->countQueries(fn () => $auditor->audit($this->subject(body: $bir)));
        $sonra = $this->countQueries(fn () => $auditor->audit($this->subject(body: $cok)));

        $this->assertSame($ilk, $sonra, "Kırk bağlantı {$sonra} sorgu attı (bir bağlantı: {$ilk}).");
    }

    // ── Adres ──

    public function test_an_invalid_slug_is_reported(): void
    {
        $this->assertHasIssue('slug.format', $this->subject(slug: 'Büyük Harfli_Slug'));
    }

    public function test_a_clean_slug_is_not_reported(): void
    {
        $this->assertNoIssue('slug.format', $this->subject());
    }

    public function test_a_slug_unrelated_to_the_title_is_reported(): void
    {
        $subject = $this->subject(title: 'Kurumsal İçerik Yönetimi', slug: 'xyz-abc-qwe');

        $this->assertHasIssue('slug.mismatch', $subject);
    }

    // ── İçerik uzunluğu ──

    public function test_an_empty_body_is_reported(): void
    {
        $this->assertHasIssue('content.empty', $this->subject(body: ''));
    }

    public function test_a_thin_body_is_reported(): void
    {
        $this->assertHasIssue('content.thin', $this->subject(body: '<p>Çok kısa bir gövde.</p>'));
    }

    public function test_a_long_enough_body_is_not_reported(): void
    {
        $this->assertNoIssue('content.thin', $this->subject());
    }

    // ── Rapor ──

    /**
     * Sağlam bir içerik hiçbir uyarı almamalı — yanlış pozitif, denetimin
     * kapatılmasının en sık sebebi.
     */
    public function test_a_healthy_subject_comes_back_clean(): void
    {
        $report = app(SeoAuditor::class)->audit($this->subject());

        $this->assertTrue(
            $report->isClean(),
            'Sağlam içerikte bulgu çıktı: ' . implode(', ', array_map(
                static fn ($issue): string => $issue->code,
                $report->issues,
            )),
        );
        $this->assertSame(100, $report->score);
        $this->assertSame('good', $report->grade());
    }

    public function test_findings_are_sorted_worst_first(): void
    {
        $report = app(SeoAuditor::class)->audit($this->subject(
            metaTitle: null,
            body: '<h1>Fazladan</h1><p>Kısa.</p><img src="/uploads/a.webp">',
        ));

        $levels = array_map(
            static fn ($issue): int => $issue->level->weight(),
            $report->issues,
        );

        $sorted = $levels;
        sort($sorted);

        $this->assertSame($sorted, $levels, 'Bulgular seviyeye göre sıralı değil.');
    }

    public function test_the_score_drops_as_findings_pile_up(): void
    {
        $auditor = app(SeoAuditor::class);

        $saglam = $auditor->audit($this->subject());
        $bozuk = $auditor->audit($this->subject(
            metaTitle: null,
            metaDescription: null,
            coverImage: null,
            body: '<h1>Fazladan</h1><img src="/uploads/a.webp">',
        ));

        $this->assertGreaterThan($bozuk->score, $saglam->score);
        $this->assertGreaterThanOrEqual(0, $bozuk->score);
    }

    /**
     * Bozuk bir kural bütün denetimi düşürmemeli: denetim bir kolaylık,
     * kaydetmenin şartı değil.
     */
    public function test_a_throwing_check_does_not_break_the_audit(): void
    {
        config(['seo.checks' => array_merge(
            [ThrowingSeoCheck::class],
            (array) config('seo.checks'),
        )]);

        $report = app(SeoAuditor::class)->audit($this->subject(metaTitle: null));

        $this->assertNotNull($this->issue($report->issues, 'meta.title.missing'));
    }

    // ── Denetim ucu ──

    public function test_the_endpoint_audits_unsaved_content(): void
    {
        $response = $this->actingAs($this->admin())->postJson('/admin/seo/denetle', [
            'locale'     => 'tr',
            'type'       => 'blog_post',
            'title'      => 'Kısa',
            'meta_title' => '',
            'body'       => '<h1>Fazladan</h1>',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $codes = array_column($response->json('report.issues'), 'code');

        $this->assertContains('heading.h1.multiple', $codes);
        $this->assertContains('meta.title.missing', $codes);
    }

    public function test_the_endpoint_is_closed_to_users_without_content_rights(): void
    {
        $user = User::create([
            'first_name' => 'Yetkisiz', 'last_name' => 'Kisi',
            'email' => 'seo-yetkisiz@example.test', 'password' => 'sifre-123456', 'is_active' => true,
        ]);
        $user->markEmailAsVerified();
        $user->roles()->attach(Role::where('slug', 'user')->firstOrFail()->id);

        $this->actingAs($user->fresh())
            ->postJson('/admin/seo/denetle', ['locale' => 'tr', 'title' => 'Deneme'])
            ->assertForbidden();
    }

    public function test_the_endpoint_accepts_a_half_filled_form(): void
    {
        // Denetimin en çok işe yarayacağı an, formun yarım olduğu an.
        $this->actingAs($this->admin())
            ->postJson('/admin/seo/denetle', ['locale' => 'tr'])
            ->assertOk();
    }

    // ── Ekran ──

    public function test_the_overview_screen_lists_the_worst_content_first(): void
    {
        Page::factory()->create([
            'locale' => 'tr', 'title' => 'İyi Sayfa', 'slug' => 'iyi-sayfa',
            'meta_title' => 'Kurumsal Sitede İçerik Yönetimi Rehberi',
            'meta_description' => str_repeat('Bu sayfa içerik yönetimini anlatıyor. ', 3),
            'image' => 'sliders/a.webp',
            'content' => '<h2>Bölüm</h2><p>' . str_repeat('Kurumsal içerik yönetimi düzenli olmalı. ', 40) . '</p>',
        ]);

        Page::factory()->create([
            'locale' => 'tr', 'title' => 'Kötü Sayfa', 'slug' => 'kotu-sayfa',
            'meta_title' => null, 'meta_description' => null, 'image' => null,
            'content' => '<h1>Fazladan</h1><img src="/uploads/a.webp">',
        ]);

        $response = $this->actingAs($this->admin())->get('/admin/seo');

        $response->assertOk();

        $html = (string) $response->getContent();

        $this->assertLessThan(
            strpos($html, 'İyi Sayfa'),
            strpos($html, 'Kötü Sayfa'),
            'Düşük puanlı içerik listenin başında değil.',
        );
    }

    public function test_the_overview_screen_can_be_filtered_by_level(): void
    {
        Page::factory()->create([
            'locale' => 'tr', 'title' => 'Hatalı Sayfa', 'slug' => 'hatali-sayfa',
            'content' => '<h1>Fazladan</h1>',
        ]);

        $response = $this->actingAs($this->admin())->get('/admin/seo?level=error');

        $response->assertOk();
        $response->assertSee('Hatalı Sayfa');
    }

    // ── Sınırların tek kaynağı ──

    /**
     * Sunucu kuralı, formdaki sayaç ve denetleyici aynı sayıyı okumalı.
     * Eskiden üçü ayrışıyordu: sayfa formu 70 karaktere izin verirken sayacı
     * 60 gösteriyordu.
     */
    public function test_the_character_limits_come_from_one_place(): void
    {
        config(['seo.title.max' => 55]);

        $response = $this->actingAs($this->admin())->get('/admin/pages/create');

        $response->assertOk();
        $response->assertSee('validate[maxSize[55]]', escape: false);
        $response->assertSee('/55', escape: false);

        $rules = (new \App\Http\Requests\Admin\StoreTranslatedPageRequest())->rules();

        $this->assertContains('max:55', $rules['translations.tr.meta_title'] ?? []);
    }

    // ── Yardımcılar ──

    private function subject(
        string $title = 'Kurumsal Sitede İçerik Yönetimi',
        string $slug = 'kurumsal-sitede-icerik-yonetimi',
        ?string $body = null,
        ?string $metaTitle = 'Kurumsal Sitede İçerik Yönetimi Rehberi',
        ?string $metaDescription = 'Kurumsal bir sitede içeriğin nasıl yönetileceğini, hangi adımların izleneceğini ve sık yapılan hataları anlatan kısa bir rehber.',
        ?string $coverImage = 'sliders/kapak.webp',
    ): SeoSubject {
        return new SeoSubject(
            locale: 'tr',
            title: $title,
            slug: $slug,
            body: $body ?? '<h2>Bölüm</h2><p>' . str_repeat('Kurumsal içerik yönetimi düzenli olmalı. ', 40) . '</p>',
            metaTitle: $metaTitle,
            metaDescription: $metaDescription,
            coverImage: $coverImage,
            type: 'page',
        );
    }

    private function assertHasIssue(string $code, SeoSubject $subject, ?SeoLevel $level = null): void
    {
        $report = app(SeoAuditor::class)->audit($subject);
        $issue = $this->issue($report->issues, $code);

        $this->assertNotNull($issue, "{$code} bulgusu çıkmadı. Çıkanlar: " . implode(', ', array_map(
            static fn ($i): string => $i->code,
            $report->issues,
        )));

        if ($level !== null) {
            $this->assertSame($level, $issue->level, "{$code} beklenen seviyede değil.");
        }
    }

    private function assertNoIssue(string $code, SeoSubject $subject): void
    {
        $report = app(SeoAuditor::class)->audit($subject);

        $this->assertNull(
            $this->issue($report->issues, $code),
            "{$code} bulgusu beklenmiyordu ama çıktı.",
        );
    }

    /**
     * @param list<\App\Support\Seo\SeoIssue> $issues
     */
    private function issue(array $issues, string $code): ?\App\Support\Seo\SeoIssue
    {
        foreach ($issues as $issue) {
            if ($issue->code === $code) {
                return $issue;
            }
        }

        return null;
    }

    /**
     * @param callable(): mixed $work
     */
    private function countQueries(callable $work): int
    {
        $count = 0;

        DB::listen(static function () use (&$count): void {
            ++$count;
        });

        $work();

        return $count;
    }

    private function admin(): User
    {
        $user = User::create([
            'first_name' => 'Seo', 'last_name' => 'Yonetici',
            'email' => 'seo@example.test', 'password' => 'sifre-123456', 'is_active' => true,
        ]);
        $user->markEmailAsVerified();
        $user->roles()->attach(Role::where('slug', 'admin')->firstOrFail()->id);

        return $user->fresh();
    }
}

/**
 * Bilerek patlayan kural — motorun onu atlayıp devam ettiğini sınamak için.
 */
final class ThrowingSeoCheck implements \App\Services\Seo\SeoCheck
{
    /** @return list<\App\Support\Seo\SeoIssue> */
    public function run(SeoSubject $subject): array
    {
        throw new \RuntimeException('bilerek patladı');
    }
}
