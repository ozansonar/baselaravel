<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Language;
use App\Models\MailTemplate;
use App\Models\Role;
use App\Models\User;
use App\Services\LanguageService;
use App\Services\MailTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mail şablonları listesi: süzgeçler, sayaçlar ve "varsayılandan farklı mı"
 * ayrımı.
 *
 * Şablon sayısı az ama içerikleri uzun; hangi şablonun elle değiştirildiğini
 * görmek listeye bakan kişinin ilk sorusu. Buradaki testler o ayrımın
 * girinti farklarından etkilenmediğini ve süzgeçlerin gerçekten daralttığını
 * doğruluyor.
 */
class MailTemplateIndexTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seedAuthorization();

        // Tek testte listeye birden çok kez bakılabiliyor; her seferinde yeni
        // bir kullanıcı açmak benzersiz e-posta kuralına takılırdı.
        $admin = User::firstOrCreate(
            ['email' => 'template-admin@example.com'],
            [
                'first_name' => 'Şablon',
                'last_name'  => 'Yöneticisi',
                'password'   => 'password',
                'is_active'  => true,
            ],
        );

        $admin->roles()->syncWithoutDetaching(Role::where('slug', 'admin')->firstOrFail());

        return $admin;
    }

    public function test_index_lists_every_template(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.mail-templates.index'))
            ->assertOk()
            ->assertSee('Hoş Geldiniz')
            ->assertSee('welcome')
            ->assertSee('Şablonlar gönderim anında doldurulur.');
    }

    /**
     * Listelenen kartlar view verisinden okunuyor: sayfada süzgeç seçenekleri
     * de şablon adları geçtiği için ham HTML araması yanıltıcı olurdu.
     *
     * @return array<int, string>
     */
    private function listedNames(array $query): array
    {
        return $this->actingAs($this->admin())
            ->get(route('admin.mail-templates.index', $query))
            ->assertOk()
            ->viewData('templates')
            ->pluck('name')
            ->all();
    }

    /**
     * Liste her zaman tek bir dile bakıyor.
     *
     * Şablonun her dilde bir satırı var; süzgeç olmasaydı aynı ad ekranda beş
     * kez görünürdü ve hangisine tıklandığı belirsiz kalırdı.
     */
    public function test_the_list_shows_one_language_at_a_time(): void
    {
        Language::firstOrCreate(
            ['code' => 'en'],
            ['name' => 'English', 'is_active' => true, 'is_default' => false],
        );
        app(MailTemplateService::class)->syncLocale('en');

        $default = app(LanguageService::class)->defaultCode();
        $keys = MailTemplate::where('locale', $default)->count();

        $this->assertCount($keys, $this->listedNames([]));
        $this->assertCount($keys, $this->listedNames(['locale' => 'en']));
    }

    /**
     * Tanınmayan bir dil kodu boş liste değil, varsayılan dil demeli.
     */
    public function test_an_unknown_language_falls_back_to_the_default(): void
    {
        $filters = $this->actingAs($this->admin())
            ->get(route('admin.mail-templates.index', ['locale' => 'zz']))
            ->assertOk()
            ->viewData('filters');

        $this->assertSame(app(LanguageService::class)->defaultCode(), $filters['locale']);
    }

    public function test_search_narrows_the_list(): void
    {
        $this->assertSame(['Hoş Geldiniz'], $this->listedNames(['search' => 'welcome']));
    }

    public function test_status_filter_narrows_the_list(): void
    {
        MailTemplate::where('key', 'welcome')->update(['is_active' => false]);

        $this->assertSame(['Hoş Geldiniz'], $this->listedNames(['status' => 'inactive']));
    }

    public function test_variable_filter_keeps_only_templates_using_it(): void
    {
        $this->assertSame(['Şifre Sıfırlama'], $this->listedNames(['variable' => 'reset_url']));
    }

    /**
     * Kurulumda yazılan içerik ile varsayılan aynı metni farklı girintilerle
     * tutuyor; boşluk farkı şablonu "özelleştirilmiş" saymamalı.
     */
    public function test_freshly_installed_templates_are_not_marked_customized(): void
    {
        $service = app(MailTemplateService::class);

        foreach (MailTemplate::all() as $template) {
            $this->assertFalse(
                $service->isCustomized($template),
                "{$template->key} kurulum içeriğiyle özelleştirilmiş sayıldı.",
            );
        }

        $this->assertSame(0, $service->stats()['customized']);
    }

    public function test_edited_template_is_marked_customized(): void
    {
        $template = MailTemplate::where('key', 'welcome')->firstOrFail();
        $template->update(['subject' => 'Yepyeni bir konu']);

        $service = app(MailTemplateService::class);

        $this->assertTrue($service->isCustomized($template->fresh()));
        $this->assertSame(1, $service->stats()['customized']);
    }

    public function test_origin_filter_separates_customized_from_default(): void
    {
        // Şablonun her dilde bir satırı var ve liste bir dile bakıyor; dili
        // söylemeyen bir sorgu başka bir dilin satırını değiştirebilirdi.
        MailTemplate::where('key', 'welcome')
            ->where('locale', app(LanguageService::class)->defaultCode())
            ->firstOrFail()
            ->update(['subject' => 'Yepyeni bir konu']);

        $this->assertSame(['Hoş Geldiniz'], $this->listedNames(['origin' => 'customized']));
    }

    /**
     * Sekme sayıları durum dışındaki süzgeçlere göre: "Aktif 1" yazıyorsa o
     * sekmeye basınca gerçekten 1 kart gelmeli.
     */
    public function test_status_counts_respect_the_other_filters(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('admin.mail-templates.index', ['variable' => 'reset_url']))
            ->assertOk();

        $counts = $response->viewData('statusCounts');

        $this->assertSame(1, $counts['all']);
        $this->assertSame(1, $counts['active']);
        $this->assertSame(0, $counts['inactive']);
    }

    public function test_sort_by_key_orders_templates(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('admin.mail-templates.index', ['sort' => 'key']))
            ->assertOk();

        $keys = $response->viewData('templates')->pluck('key')->all();
        $sorted = $keys;
        sort($sorted);

        $this->assertSame($sorted, $keys);
    }

    /**
     * Tanınmayan sıralama değeri sorguyu bozmamalı, ada göre sıralamaya düşmeli.
     */
    public function test_unknown_sort_falls_back_to_name(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('admin.mail-templates.index', ['sort' => 'drop table']))
            ->assertOk();

        $this->assertSame('', $response->viewData('filters')['sort']);
    }

    public function test_filter_chips_and_empty_state(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.mail-templates.index', ['search' => 'böyle bir şablon yok']))
            ->assertOk()
            ->assertSee('Açık süzgeçler')
            ->assertSee('Arama süzgecini kaldır')
            ->assertSee('Bu filtreyle eşleşen şablon yok.');
    }

    /**
     * Arama joker karakter almamalı: "%" yazan biri tüm listeyi değil, içinde
     * yüzde işareti geçen şablonları görür.
     */
    public function test_search_treats_wildcards_as_plain_text(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.mail-templates.index', ['search' => '%']))
            ->assertOk()
            ->assertSee('Bu filtreyle eşleşen şablon yok.');
    }

    public function test_preview_returns_rendered_html_with_example_values(): void
    {
        $template = MailTemplate::where('key', 'welcome')->firstOrFail();

        $response = $this->actingAs($this->admin())
            ->postJson(route('admin.mail-templates.preview', $template))
            ->assertOk()
            ->assertJsonStructure(['subject', 'html']);

        // Örnek değerler yerine değişken adı kalırsa önizleme işe yaramaz.
        $this->assertStringNotContainsString('{user_name}', $response->json('html'));
    }
}
