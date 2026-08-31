<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PopupDisplayMode;
use App\Enums\PopupSize;
use App\Models\Popup;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Duyuru (popup) modülü.
 *
 * Üç kusur vardı. Duyuru bir kez görülünce oturum boyunca bir daha
 * çıkmıyordu ve yöneticinin bunu değiştirme yolu yoktu; yanlışlıkla kapatan
 * ziyaretçi onu bir daha göremiyordu. Düzenleme ekranı kayıtlı boyutu ve
 * sayfa seçimini okumuyor, her açılışta varsayılanı gösteriyordu — kaydet
 * denince de kullanıcının seçmediği değer yazılıyordu. Silme onayında başlık
 * <strong> ile gömülüydü ve etiket ekranda metin olarak görünüyordu.
 */
final class PopupModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        app(\App\Services\LanguageService::class)->clearCache();
        $this->seedAuthorization();

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('slug', 'admin')->firstOrFail());
    }

    private function popup(array $overrides = []): Popup
    {
        return Popup::create(array_merge([
            'locale'        => 'tr',
            'lang_group_id' => (string) Str::uuid(),
            'title'         => 'Bahar Kampanyası',
            'description'   => 'Açıklama',
            'size'          => PopupSize::Lg,
            'display_mode'  => PopupDisplayMode::Session,
            'pages'         => ['home'],
            'is_active'     => true,
            'sort_order'    => 0,
        ], $overrides));
    }

    // ── Gösterim sıklığı ──

    public function test_a_popup_keeps_the_old_behaviour_unless_told_otherwise(): void
    {
        $this->assertSame(PopupDisplayMode::Session, $this->popup()->display_mode);
        $this->assertSame('session', PopupDisplayMode::Session->storage());
    }

    /**
     * Her mod kendi kuralını taşımalı: depo yoksa duyuru her zaman görünür,
     * "bir kez" görüldüğü an biter, ötekiler kapatılınca.
     */
    public function test_each_mode_carries_its_own_rule(): void
    {
        $this->assertNull(PopupDisplayMode::Always->storage(), 'Her zaman görünen duyuru hiçbir yere yazılmamalı');
        $this->assertSame('session', PopupDisplayMode::Session->storage());
        $this->assertSame('local', PopupDisplayMode::Once->storage());
        $this->assertSame('local', PopupDisplayMode::UntilClosed->storage());

        $this->assertFalse(PopupDisplayMode::Once->remembersOnClose(), '"Bir kez" görüldüğü an bitmeli');
        $this->assertTrue(PopupDisplayMode::UntilClosed->remembersOnClose());
        $this->assertTrue(PopupDisplayMode::Session->remembersOnClose());
    }

    /** Kural ön yüze inmeli; tarayıcı onu okuyor. */
    public function test_the_rule_reaches_the_page(): void
    {
        $this->popup(['display_mode' => PopupDisplayMode::UntilClosed]);

        $html = (string) $this->get('/tr')->assertOk()->getContent();

        $this->assertStringContainsString('data-popup-store="local"', $html);
        $this->assertStringContainsString('data-popup-remember="close"', $html);
    }

    public function test_an_always_visible_popup_is_never_remembered(): void
    {
        $this->popup(['display_mode' => PopupDisplayMode::Always]);

        $html = (string) $this->get('/tr')->assertOk()->getContent();

        $this->assertStringContainsString('data-popup-store=""', $html);
    }

    public function test_a_once_only_popup_is_remembered_the_moment_it_shows(): void
    {
        $this->popup(['display_mode' => PopupDisplayMode::Once]);

        $html = (string) $this->get('/tr')->assertOk()->getContent();

        $this->assertStringContainsString('data-popup-store="local"', $html);
        $this->assertStringContainsString('data-popup-remember="show"', $html);
    }

    /** Tarayıcı tarafı kuralı gerçekten okuyor mu — davranış JS'te yazılı. */
    public function test_the_browser_side_reads_the_rule_instead_of_assuming(): void
    {
        $js = (string) file_get_contents(public_path('js/app.js'));

        $this->assertStringContainsString('data-popup-store', $js);
        $this->assertStringContainsString('popupRemember', $js);
        $this->assertStringContainsString('window.localStorage', $js);
        // Eski hâlinde sessionStorage'a sabitlenmişti.
        $this->assertStringNotContainsString("var store = window.sessionStorage;", $js);
    }

    public function test_the_admin_form_offers_every_mode(): void
    {
        $popup = $this->popup();

        $html = (string) $this->actingAs($this->admin)
            ->get(route('admin.popups.edit', $popup))->assertOk()->getContent();

        foreach (PopupDisplayMode::cases() as $mode) {
            $this->assertStringContainsString($mode->label(), $html, "{$mode->value} seçeneği formda yok");
        }
    }

    // ── Düzenleme ekranı kayıtlı değeri okumalı ──

    public function test_the_edit_screen_shows_the_saved_size(): void
    {
        $popup = $this->popup(['size' => PopupSize::Xl]);

        $tr = $this->languagePane($popup, 'tr');

        $this->assertMatchesRegularExpression('/value="xl"[^>]*selected/', $tr);
        $this->assertDoesNotMatchRegularExpression('/value="md"[^>]*selected/', $tr);
    }

    public function test_the_edit_screen_shows_the_saved_display_mode(): void
    {
        $popup = $this->popup(['display_mode' => PopupDisplayMode::UntilClosed]);

        $tr = $this->languagePane($popup, 'tr');

        $this->assertStringContainsString('value="until_closed" data-hint="', $tr);
        $this->assertMatchesRegularExpression('/value="until_closed"[^>]*selected/', $tr);
    }

    public function test_the_edit_screen_shows_the_saved_pages(): void
    {
        $popup = $this->popup(['pages' => ['blog', 'contact']]);

        $tr = $this->languagePane($popup, 'tr');

        $this->assertMatchesRegularExpression('/value="blog"[^>]*checked/', $tr);
        $this->assertMatchesRegularExpression('/value="contact"[^>]*checked/', $tr);
        // "Tümü" seçilmemişti; ekran onu işaretlememeli.
        $this->assertDoesNotMatchRegularExpression('/value="all"[^>]*checked/', $tr);
    }

    /**
     * Onay kutusu kimliği diller arasında çakışıyordu: İngilizce sekmedeki
     * etikete tıklamak Türkçe kutuyu işaretliyordu.
     */
    public function test_the_page_checkboxes_do_not_share_ids_across_languages(): void
    {
        $html = (string) $this->actingAs($this->admin)
            ->get(route('admin.popups.create'))->assertOk()->getContent();

        preg_match_all('/id="(page_[a-z_]+_[a-z]{2})"/', $html, $matches);

        $this->assertNotEmpty($matches[1], 'Sayfa onay kutuları bulunamadı');
        $this->assertSame(
            count($matches[1]),
            count(array_unique($matches[1])),
            'Aynı kimlik birden çok dilde kullanılıyor',
        );
    }

    // ── Silme onayı ──

    public function test_the_delete_confirmation_carries_no_markup(): void
    {
        $html = (string) $this->actingAs($this->admin)
            ->get(route('admin.popups.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString("'<strong>' + title", $html);
        // Başlık, pencerenin kendi ayrıntı kutusuna geçiyor.
        $this->assertStringContainsString('detailTitle: title', $html);
    }

    /**
     * Düzenleme ekranının bir dile ait sekmesi.
     *
     * Ekran her dil için ayrı bir sekme basıyor ve çevrilmemiş dilin sekmesi
     * varsayılanları gösteriyor — haklı olarak. Denetim sayfanın tamamına
     * bakarsa o varsayılanları kayıtlı değer sanır.
     */
    private function languagePane(Popup $popup, string $locale): string
    {
        $html = (string) preg_replace(
            '/\s+/',
            ' ',
            (string) $this->actingAs($this->admin)->get(route('admin.popups.edit', $popup))->assertOk()->getContent(),
        );

        $start = strpos($html, 'id="popupLangTabs-' . $locale . '"');

        $this->assertNotFalse($start, "{$locale} sekmesi bulunamadı");

        // Sonraki sekmenin başlangıcı bu sekmenin sonu; sekme gövdeleri
        // sırayla basılıyor.
        $next = strpos($html, 'id="popupLangTabs-', (int) $start + 10);

        return $next === false
            ? substr($html, (int) $start)
            : substr($html, (int) $start, $next - (int) $start);
    }
}
