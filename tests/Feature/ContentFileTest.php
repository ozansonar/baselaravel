<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AttachableContent;
use App\Enums\FileKind;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\ContentFile;
use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use App\Services\ContentFileService;
use App\Services\UploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * İçeriklere iliştirilen dosyalar.
 *
 * Ek, dil grubuna değil o dilin blog_posts satırına bağlı: Türkçe yazının kırk
 * eki varken İngilizcesinin hiç eki olmaması bu yüzden mümkün. Dosyalar formla
 * birlikte değil kendi istekleriyle gidiyor, dolayısıyla iki bağlanma yolu var
 * ve ikisi de burada sınanıyor — çevirisi olan dilde doğrudan, olmayan dilde
 * belirteçle.
 *
 * Blog yazısı ile sayfa aynı tabloyu, aynı servisi ve aynı ekranı paylaşıyor
 * (bağ polimorfik); sayfa tarafı da burada, ayrı bir dosyada değil.
 */
class ContentFileTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        app(\App\Services\LanguageService::class)->clearCache();
        $this->seedAuthorization();

        $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Yönetici']);

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($role);

        $this->actingAs($this->admin);
    }

    private function category(string $locale = 'tr'): BlogCategory
    {
        return BlogCategory::create([
            'locale' => $locale,
            'name'   => 'Duyurular ' . $locale,
            'slug'   => 'duyurular-' . $locale,
        ]);
    }

    private function turkishPost(): BlogPost
    {
        return BlogPost::create([
            'locale'           => 'tr',
            'blog_category_id' => $this->category()->id,
            'user_id'          => $this->admin->id,
            'title'            => 'Yeni Duyuru',
            'slug'             => 'yeni-duyuru',
            'body'             => '<p>Gövde</p>',
            'status'           => 'published',
            'published_at'     => now()->subDay(),
        ]);
    }

    private function upload(string $name, string $content = 'veri'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    // ── Yükleme ──

    public function test_a_file_uploaded_to_an_existing_translation_attaches_to_it_at_once(): void
    {
        $post = $this->turkishPost();

        $response = $this->postJson(route('admin.content-files.upload'), [
            'file'         => $this->upload('rapor.csv', "ad;soyad\n"),
            'attachable_type' => AttachableContent::BlogPost->value,
            'attachable_id'   => $post->id,
        ]);

        $response->assertOk();
        // İçerik kaydedilmeden ek yerinde: kimlik dönüyor, belirteç değil.
        $response->assertJsonPath('kind', FileKind::Spreadsheet->value);
        $this->assertNull($response->json('token'));

        $file = ContentFile::sole();

        $this->assertSame($post->id, $file->attachable_id);
        $this->assertSame('rapor.csv', $file->original_name);
        $this->assertSame('csv', $file->extension);
    }

    public function test_a_file_uploaded_without_a_translation_waits_with_a_token(): void
    {
        $response = $this->postJson(route('admin.content-files.upload'), [
            'file'            => $this->upload('brosur.pdf'),
            'attachable_type' => AttachableContent::BlogPost->value,
        ]);

        $response->assertOk();
        $this->assertNull($response->json('id'));
        $this->assertNotNull($response->json('token'));

        $file = ContentFile::sole();

        $this->assertNull($file->attachable_id);
        $this->assertSame($this->admin->id, $file->user_id);
    }

    public function test_an_extension_outside_the_whitelist_is_refused(): void
    {
        $this->postJson(route('admin.content-files.upload'), [
            'file'            => $this->upload('shell.php', '<?php echo 1;'),
            'attachable_type' => AttachableContent::BlogPost->value,
        ])->assertStatus(422);

        // Çift uzantı da geçmiyor: son uzantı neyse o sayılıyor.
        $this->postJson(route('admin.content-files.upload'), [
            'file'            => $this->upload('resim.jpg.php', 'x'),
            'attachable_type' => AttachableContent::BlogPost->value,
        ])->assertStatus(422);

        $this->assertSame(0, ContentFile::count());
    }

    public function test_a_second_extension_cannot_survive_onto_disk(): void
    {
        $post = $this->turkishPost();

        // Apache, mod_mime açıkken "rapor.php.xlsx" adlı bir dosyayı PHP olarak
        // çalıştırabiliyor. Diskteki ad kullanıcınınkinden türetiliyor ama
        // noktalarından arındırılıyor: geriye tek uzantı kalıyor.
        $this->postJson(route('admin.content-files.upload'), [
            'file'         => $this->upload('rapor.php.xlsx'),
            'attachable_type' => AttachableContent::BlogPost->value,
            'attachable_id'   => $post->id,
        ])->assertOk();

        $stored = basename(ContentFile::sole()->path);

        $this->assertSame(1, substr_count($stored, '.'), "Diskteki ad birden fazla uzantı taşıyor: {$stored}");
        $this->assertStringEndsWith('.xlsx', $stored);
        $this->assertStringNotContainsString('.php', $stored);
    }

    // ── Kaydetme ──

    public function test_a_pending_file_is_attached_to_the_row_its_language_creates(): void
    {
        $token = $this->postJson(route('admin.content-files.upload'), [
            'file'            => $this->upload('kilavuz.pdf'),
            'attachable_type' => AttachableContent::BlogPost->value,
        ])->json('token');

        $category = $this->category('en');

        $this->post(route('admin.blog-posts.store'), [
            'translations' => [
                'en' => [
                    'title'            => 'Announcement',
                    'body'             => '<p>Body</p>',
                    'blog_category_id' => $category->id,
                    'status'           => 'draft',
                    'file_tokens'      => [$token],
                ],
            ],
        ])->assertRedirect();

        $english = BlogPost::where('locale', 'en')->sole();
        $file = ContentFile::sole();

        $this->assertSame($english->id, $file->attachable_id);
        $this->assertNull($file->token);
    }

    public function test_a_pending_file_whose_language_never_got_a_row_is_discarded(): void
    {
        $token = $this->postJson(route('admin.content-files.upload'), [
            'file'            => $this->upload('bosta.pdf'),
            'attachable_type' => AttachableContent::BlogPost->value,
        ])->json('token');

        $path = ContentFile::sole()->path;

        // İngilizce blok yalnızca dosya taşıyor: o dilde satır doğmuyor, ekin
        // bağlanacağı yer yok. Dosya diskte bırakılsaydı sahipsiz kalırdı.
        //
        // Bu yol formdan geçmiyor — form yalnızca ekrandaki sekmeyi gönderiyor
        // ve gönderilen sekmenin başlığı zorunlu. Servis yine de kendi başına
        // doğru davranmalı; ekleri buraya taşıyan tek yer burası.
        app(\App\Services\BlogService::class)->createTranslated([
            'tr' => [
                'title'            => 'Duyuru',
                'body'             => '<p>Gövde</p>',
                'blog_category_id' => $this->category()->id,
                'status'           => 'draft',
            ],
            'en' => ['file_tokens' => [$token]],
        ], ['user_id' => $this->admin->id]);

        $this->assertSame(0, ContentFile::withTrashed()->count());
        $this->assertFileDoesNotExist(UploadService::basePath($path));
    }

    public function test_a_pending_file_survives_a_validation_error(): void
    {
        $token = $this->postJson(route('admin.content-files.upload'), [
            'file'            => $this->upload('kilavuz.pdf'),
            'attachable_type' => AttachableContent::BlogPost->value,
        ])->json('token');

        // Başlık boş: kayıt düşüyor ve form geri geliyor. Yükleme satırını JS
        // çizdiği için sayfa yenilenince kaybolurdu; belirteç eski girdiden
        // okunup satır yeniden basılıyor.
        $this->from(route('admin.blog-posts.create'))
            ->post(route('admin.blog-posts.store'), [
                'translations' => [
                    'en' => [
                        'title'            => '',
                        'body'             => '<p>Body</p>',
                        'blog_category_id' => $this->category('en')->id,
                        'file_tokens'      => [$token],
                    ],
                ],
            ])
            ->assertSessionHasErrors('translations.en.title');

        $this->get(route('admin.blog-posts.create'))
            ->assertOk()
            ->assertSee('kilavuz.pdf')
            ->assertSee('value="' . $token . '"', false);
    }

    // ── Dil bazlı olma ──

    public function test_attachments_belong_to_one_language_only(): void
    {
        $turkish = $this->turkishPost();

        $english = BlogPost::create([
            'locale'           => 'en',
            'lang_group_id'    => $turkish->lang_group_id,
            'blog_category_id' => $this->category('en')->id,
            'user_id'          => $this->admin->id,
            'title'            => 'Announcement',
            'slug'             => 'announcement',
            'body'             => '<p>Body</p>',
            'status'           => 'published',
            'published_at'     => now()->subDay(),
        ]);

        foreach (['bir.pdf', 'iki.xlsx', 'uc.png'] as $name) {
            $this->postJson(route('admin.content-files.upload'), [
                'file'         => $this->upload($name),
                'attachable_type' => AttachableContent::BlogPost->value,
                'attachable_id'   => $turkish->id,
            ])->assertOk();
        }

        $this->assertCount(3, $turkish->files()->get());
        $this->assertCount(0, $english->files()->get());
    }

    // ── Kaldırma ──

    public function test_removing_an_attachment_deletes_the_file_from_disk(): void
    {
        $post = $this->turkishPost();

        $this->postJson(route('admin.content-files.upload'), [
            'file'         => $this->upload('gecici.txt'),
            'attachable_type' => AttachableContent::BlogPost->value,
            'attachable_id'   => $post->id,
        ])->assertOk();

        $file = ContentFile::sole();
        $path = UploadService::basePath($file->path);

        $this->assertFileExists($path);

        $this->deleteJson(route('admin.content-files.destroy', $file))->assertOk();

        $this->assertSame(0, ContentFile::withTrashed()->count());
        $this->assertFileDoesNotExist($path);
    }

    public function test_a_soft_deleted_post_hides_its_files_and_restoring_brings_them_back(): void
    {
        $post = $this->turkishPost();

        $this->postJson(route('admin.content-files.upload'), [
            'file'         => $this->upload('ek.pdf'),
            'attachable_type' => AttachableContent::BlogPost->value,
            'attachable_id'   => $post->id,
        ])->assertOk();

        $path = UploadService::basePath(ContentFile::sole()->path);

        $post->delete();

        $this->assertSame(0, ContentFile::count());
        $this->assertSame(1, ContentFile::onlyTrashed()->count());
        // Yumuşak silme dosyaya dokunmuyor; geri alınabilir olması bunu gerektiriyor.
        $this->assertFileExists($path);

        $post->restore();

        $this->assertSame(1, ContentFile::count());
    }

    // ── Sayfa ekleri (aynı düzen, ayrı içerik türü) ──

    private function turkishPage(): Page
    {
        return Page::create([
            'locale'       => 'tr',
            'title'        => 'Hakkımızda',
            'slug'         => 'hakkimizda',
            'content'      => '<p>Gövde</p>',
            'status'       => 'published',
            'published_at' => now()->subDay(),
        ]);
    }

    public function test_a_page_carries_its_own_attachments(): void
    {
        $page = $this->turkishPage();

        $this->postJson(route('admin.content-files.upload'), [
            'file'            => $this->upload('kurumsal-sunum.pptx'),
            'attachable_type' => AttachableContent::Page->value,
            'attachable_id'   => $page->id,
        ])->assertOk()->assertJsonPath('kind', FileKind::Presentation->value);

        $file = ContentFile::sole();

        $this->assertSame(Page::class, $file->attachable_type);
        $this->assertSame($page->id, $file->attachable_id);
        $this->assertCount(1, $page->files()->get());
    }

    public function test_page_attachments_belong_to_one_language_only(): void
    {
        $turkish = $this->turkishPage();

        $english = Page::create([
            'locale'        => 'en',
            'lang_group_id' => $turkish->lang_group_id,
            'title'         => 'About Us',
            'slug'          => 'about-us',
            'content'       => '<p>Body</p>',
            'status'        => 'published',
            'published_at'  => now()->subDay(),
        ]);

        foreach (['bir.pdf', 'iki.xlsx'] as $name) {
            $this->postJson(route('admin.content-files.upload'), [
                'file'            => $this->upload($name),
                'attachable_type' => AttachableContent::Page->value,
                'attachable_id'   => $turkish->id,
            ])->assertOk();
        }

        $this->assertCount(2, $turkish->files()->get());
        $this->assertCount(0, $english->files()->get());
    }

    public function test_a_pending_file_is_attached_to_the_page_its_language_creates(): void
    {
        $token = $this->postJson(route('admin.content-files.upload'), [
            'file'            => $this->upload('brosur.pdf'),
            'attachable_type' => AttachableContent::Page->value,
        ])->json('token');

        $this->post(route('admin.pages.store'), [
            'translations' => [
                'tr' => [
                    'title'       => 'Hakkımızda',
                    'content'     => '<p>Gövde</p>',
                    'status'      => 'draft',
                    'file_tokens' => [$token],
                ],
            ],
        ])->assertRedirect();

        $page = Page::where('locale', 'tr')->sole();
        $file = ContentFile::sole();

        $this->assertSame($page->id, $file->attachable_id);
        $this->assertNull($file->token);
    }

    public function test_a_soft_deleted_page_hides_its_files_and_restoring_brings_them_back(): void
    {
        $page = $this->turkishPage();

        $this->postJson(route('admin.content-files.upload'), [
            'file'            => $this->upload('ek.pdf'),
            'attachable_type' => AttachableContent::Page->value,
            'attachable_id'   => $page->id,
        ])->assertOk();

        $page->delete();

        $this->assertSame(0, ContentFile::count());
        $this->assertSame(1, ContentFile::onlyTrashed()->count());

        $page->restore();

        $this->assertSame(1, ContentFile::count());
    }

    public function test_a_page_attachment_is_downloadable_and_shown_on_the_page(): void
    {
        $page = $this->turkishPage();

        $this->postJson(route('admin.content-files.upload'), [
            'file'            => $this->upload('Fiyat Listesi.xlsx'),
            'attachable_type' => AttachableContent::Page->value,
            'attachable_id'   => $page->id,
        ])->assertOk();

        $file = ContentFile::sole();

        $this->get(route('content.files.download', $file))
            ->assertOk()
            ->assertDownload('Fiyat Listesi.xlsx');

        $this->get(route('pages.show', ['locale' => 'tr', 'slug' => 'hakkimizda']))
            ->assertOk()
            ->assertSee('Fiyat Listesi.xlsx')
            ->assertSee('attachments__group');
    }

    /**
     * Tür istekten okunuyor ama sınıf adı listeden geliyor: uydurulmuş bir tür
     * ekin sahibini değiştirememeli.
     */
    public function test_an_unknown_content_type_is_refused(): void
    {
        $this->postJson(route('admin.content-files.upload'), [
            'file'            => $this->upload('deneme.pdf'),
            'attachable_type' => 'user',
            'attachable_id'   => 1,
        ])->assertStatus(422);

        $this->assertSame(0, ContentFile::count());
    }

    // ── Ön yüz ──

    public function test_the_front_serves_the_file_under_the_name_it_was_uploaded_with(): void
    {
        $post = $this->turkishPost();

        $this->postJson(route('admin.content-files.upload'), [
            'file'         => $this->upload('Satis Raporu 2026.csv', "ad;soyad\n"),
            'attachable_type' => AttachableContent::BlogPost->value,
            'attachable_id'   => $post->id,
        ])->assertOk();

        $file = ContentFile::sole();

        // Diskteki ad eğik ve benzersiz; kullanıcıya kendi adıyla iniyor.
        $this->assertNotSame('Satis Raporu 2026.csv', basename($file->path));

        $this->get(route('content.files.download', $file))
            ->assertOk()
            ->assertDownload('Satis Raporu 2026.csv');
    }

    public function test_a_file_of_an_unpublished_post_is_not_served(): void
    {
        $post = $this->turkishPost();
        $post->update(['status' => 'draft']);

        $this->postJson(route('admin.content-files.upload'), [
            'file'         => $this->upload('gizli.pdf'),
            'attachable_type' => AttachableContent::BlogPost->value,
            'attachable_id'   => $post->id,
        ])->assertOk();

        $this->get(route('content.files.download', ContentFile::sole()))->assertNotFound();
    }

    public function test_attachments_are_grouped_by_kind_in_a_fixed_order(): void
    {
        $post = $this->turkishPost();

        // Yükleme sırası bilerek karışık: gruplama sırası ailelerin kendi
        // sırası olmalı, yoksa aynı yazı her açılışta başka türlü dizilirdi.
        foreach (['tablo.xlsx', 'gorsel.png', 'belge.pdf', 'ikinci.png'] as $name) {
            $this->postJson(route('admin.content-files.upload'), [
                'file'         => $this->upload($name),
                'attachable_type' => AttachableContent::BlogPost->value,
                'attachable_id'   => $post->id,
            ])->assertOk();
        }

        $grouped = app(ContentFileService::class)->groupByKind($post->files()->get());

        $this->assertSame(
            [FileKind::Image->value, FileKind::Pdf->value, FileKind::Spreadsheet->value],
            $grouped->keys()->all(),
        );
        $this->assertCount(2, $grouped[FileKind::Image->value]);
    }
}
