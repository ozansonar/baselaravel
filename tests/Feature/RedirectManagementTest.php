<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Redirect;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Yönlendirme yönetimi: ayrı form sayfaları, liste süzgeçleri ve kaydederken
 * yapılan denetimler.
 *
 * Yanlış bir yönlendirme sessiz bir arıza: sayfa ya hiç açılmaz ya da ziyaretçi
 * beklenmedik bir yere gider. Bu yüzden asıl korunan şey, kaydın kabul edilme
 * koşulları.
 */
class RedirectManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
    }

    private function admin(): User
    {
        $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);

        $user = User::create([
            'first_name' => 'Site',
            'last_name'  => 'Admin',
            'email'      => 'redirect-manager@example.test',
            'password'   => 'password',
            'is_active'  => true,
        ]);

        $user->roles()->attach($role);

        return $user;
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'old_url'     => '/eski-sayfa',
            'new_url'     => '/yeni-sayfa',
            'status_code' => 301,
            'is_active'   => '1',
            'note'        => 'Taşındı',
        ], $overrides);
    }

    // ── Ayrı form sayfaları ──

    public function test_the_form_lives_on_its_own_page(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.redirects.create'))
            ->assertOk()
            ->assertSee('Yeni Yönlendirme')
            ->assertSee('name="old_url"', false);

        $redirect = Redirect::create($this->payload(['is_active' => true]));

        $this->actingAs($admin)
            ->get(route('admin.redirects.edit', $redirect))
            ->assertOk()
            ->assertSee('/eski-sayfa')
            ->assertSee('/yeni-sayfa');
    }

    /**
     * Liste ekranı formu artık taşımıyor: pencere kaldırıldı, bağlantı verildi.
     */
    public function test_the_list_links_to_the_form_instead_of_opening_a_dialog(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('admin.redirects.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('admin.redirects.create'), $html);
        $this->assertStringNotContainsString('id="redirectModal"', $html);
    }

    /**
     * Alanlar doğrulama motorunun kurallarını taşımalı; kuralsız alan sunucuya
     * boş ya da bozuk veriyle gider.
     */
    public function test_the_form_fields_carry_validation_rules(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('admin.redirects.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-validate', $html);
        // Sınır sütun genişliğinden geliyor: redirects.old_url ve new_url 191
        // karakter (eski MySQL'in indeks anahtarı sınırı yüzünden bilerek).
        $this->assertStringContainsString('validate[required,custom[sitePath],maxSize[191]]', $html);
        $this->assertStringContainsString('validate[required,custom[redirectTarget],maxSize[191]]', $html);
        $this->assertStringContainsString('validate[maxSize[500]]', $html);
    }

    // ── Kaydetme denetimleri ──

    public function test_a_redirect_is_created_from_the_form_page(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.redirects.store'), $this->payload())
            ->assertRedirect(route('admin.redirects.index'))
            ->assertSessionHasNoErrors();

        $redirect = Redirect::firstOrFail();

        $this->assertSame('/eski-sayfa', $redirect->old_url);
        $this->assertSame('/yeni-sayfa', $redirect->new_url);
        $this->assertTrue($redirect->is_active);
    }

    /**
     * Kendine yönlenen sayfa hiç açılmaz: tarayıcı döngüyü fark edip vazgeçer.
     */
    public function test_a_redirect_to_itself_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.redirects.store'), $this->payload([
                'old_url' => '/dongu',
                'new_url' => '/dongu',
            ]))
            ->assertSessionHasErrors('new_url');

        $this->assertSame(0, Redirect::count());
    }

    /**
     * Sondaki bölü ve harf büyüklüğü aynı adresi farklı göstermemeli.
     */
    public function test_a_redirect_to_itself_is_refused_despite_a_trailing_slash(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.redirects.store'), $this->payload([
                'old_url' => '/Dongu',
                'new_url' => '/dongu/',
            ]))
            ->assertSessionHasErrors('new_url');
    }

    /**
     * İki kayıt tek tek doğru ama birlikte halka: /a → /b varken /b → /a
     * eklemek sayfayı sonsuza kadar açılmaz yapar.
     */
    public function test_a_loop_between_two_redirects_is_refused(): void
    {
        Redirect::create($this->payload([
            'old_url'   => '/a',
            'new_url'   => '/b',
            'is_active' => true,
        ]));

        $this->actingAs($this->admin())
            ->post(route('admin.redirects.store'), $this->payload([
                'old_url' => '/b',
                'new_url' => '/a',
            ]))
            ->assertSessionHasErrors('new_url');

        $this->assertSame(1, Redirect::count());
    }

    /**
     * Uzun zincir de halka olabilir: /a → /b → /c → /a.
     */
    public function test_a_longer_loop_is_refused(): void
    {
        Redirect::create($this->payload(['old_url' => '/a', 'new_url' => '/b', 'is_active' => true]));
        Redirect::create($this->payload(['old_url' => '/b', 'new_url' => '/c', 'is_active' => true]));

        $this->actingAs($this->admin())
            ->post(route('admin.redirects.store'), $this->payload([
                'old_url' => '/c',
                'new_url' => '/a',
            ]))
            ->assertSessionHasErrors('new_url');
    }

    /**
     * Halka olmayan zincir kabul edilmeli; denetim fazla ileri gitmemeli.
     */
    public function test_a_straight_chain_is_allowed(): void
    {
        Redirect::create($this->payload(['old_url' => '/a', 'new_url' => '/b', 'is_active' => true]));

        $this->actingAs($this->admin())
            ->post(route('admin.redirects.store'), $this->payload([
                'old_url' => '/c',
                'new_url' => '/a',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Redirect::count());
    }

    /**
     * Kaydı kendi üzerinde düzenlemek "zaten tanımlı" hatası vermemeli.
     */
    public function test_a_redirect_can_be_updated_without_tripping_its_own_uniqueness(): void
    {
        $redirect = Redirect::create($this->payload(['is_active' => true]));

        $this->actingAs($this->admin())
            ->put(route('admin.redirects.update', $redirect), $this->payload([
                'new_url' => '/baska-sayfa',
            ]))
            ->assertRedirect(route('admin.redirects.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('/baska-sayfa', $redirect->fresh()->new_url);
    }

    /**
     * 404 ve 410 bir yere göndermez; hedef alanı boş bırakılabilmeli.
     */
    public function test_a_gone_status_needs_no_target(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.redirects.store'), $this->payload([
                'old_url'     => '/kaldirildi',
                'new_url'     => null,
                'status_code' => 410,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNull(Redirect::firstOrFail()->new_url);
    }

    // ── Liste süzgeçleri ──

    public function test_the_list_can_be_filtered_and_sorted(): void
    {
        Redirect::create($this->payload(['old_url' => '/kullanilan', 'hit_count' => 12, 'is_active' => true]));
        Redirect::create($this->payload(['old_url' => '/kullanilmayan', 'hit_count' => 0, 'is_active' => false]));

        $admin = $this->admin();

        $kullanilan = $this->actingAs($admin)
            ->get(route('admin.redirects.index', ['usage' => 'used']))
            ->viewData('redirects');

        $kapali = $this->actingAs($admin)
            ->get(route('admin.redirects.index', ['status' => 'inactive']))
            ->viewData('redirects');

        $enCokKullanilan = $this->actingAs($admin)
            ->get(route('admin.redirects.index', ['sort' => 'hits']))
            ->viewData('redirects');

        $this->assertSame(['/kullanilan'], $kullanilan->pluck('old_url')->all());
        $this->assertSame(['/kullanilmayan'], $kapali->pluck('old_url')->all());
        $this->assertSame('/kullanilan', $enCokKullanilan->first()->old_url);
    }

    public function test_the_search_also_looks_in_the_note(): void
    {
        Redirect::create($this->payload(['old_url' => '/bir', 'note' => 'kampanya sayfası', 'is_active' => true]));
        Redirect::create($this->payload(['old_url' => '/iki', 'note' => 'eski blog', 'is_active' => true]));

        $sonuc = $this->actingAs($this->admin())
            ->get(route('admin.redirects.index', ['search' => 'kampanya']))
            ->viewData('redirects');

        $this->assertSame(['/bir'], $sonuc->pluck('old_url')->all());
    }

    /**
     * Joker karakter düz metin sayılmalı; yoksa "%" yazan biri süzgeç yaptığını
     * sanarak tüm listeye bakar.
     */
    public function test_a_wildcard_in_the_search_is_taken_literally(): void
    {
        Redirect::create($this->payload(['old_url' => '/normal', 'is_active' => true]));
        Redirect::create($this->payload(['old_url' => '/yuzde%isaret', 'is_active' => true]));

        $sonuc = $this->actingAs($this->admin())
            ->get(route('admin.redirects.index', ['search' => '%']))
            ->viewData('redirects');

        $this->assertSame(['/yuzde%isaret'], $sonuc->pluck('old_url')->all());
    }

    public function test_deleted_redirects_are_kept_out_of_the_list_until_asked_for(): void
    {
        $redirect = Redirect::create($this->payload(['is_active' => true]));
        $redirect->delete();

        $admin = $this->admin();

        $this->assertSame(
            0,
            $this->actingAs($admin)->get(route('admin.redirects.index'))->viewData('redirects')->total(),
        );

        $this->assertSame(
            1,
            $this->actingAs($admin)->get(route('admin.redirects.index', ['trashed' => 1]))->viewData('redirects')->total(),
        );
    }

    public function test_an_unknown_page_size_falls_back_to_the_default(): void
    {
        $this->assertSame(
            25,
            $this->actingAs($this->admin())
                ->get(route('admin.redirects.index', ['per_page' => 9999]))
                ->viewData('redirects')
                ->perPage(),
        );
    }

    /**
     * Uydurulmuş bir sıralama sütun adı olarak sorguya girmemeli.
     */
    public function test_an_unknown_sort_is_ignored(): void
    {
        Redirect::create($this->payload(['is_active' => true]));

        $this->actingAs($this->admin())
            ->get(route('admin.redirects.index', ['sort' => 'old_url); drop table redirects;--']))
            ->assertOk();

        $this->assertSame(1, Redirect::count());
    }
}
