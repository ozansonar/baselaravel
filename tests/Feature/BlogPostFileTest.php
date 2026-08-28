<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\FileKind;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogPostFile;
use App\Models\Role;
use App\Models\User;
use App\Services\BlogPostFileService;
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
 */
class BlogPostFileTest extends TestCase
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

        $response = $this->postJson(route('admin.blog-posts.files.upload'), [
            'file'         => $this->upload('rapor.csv', "ad;soyad\n"),
            'blog_post_id' => $post->id,
        ]);

        $response->assertOk();
        // İçerik kaydedilmeden ek yerinde: kimlik dönüyor, belirteç değil.
        $response->assertJsonPath('kind', FileKind::Spreadsheet->value);
        $this->assertNull($response->json('token'));

        $file = BlogPostFile::sole();

        $this->assertSame($post->id, $file->blog_post_id);
        $this->assertSame('rapor.csv', $file->original_name);
        $this->assertSame('csv', $file->extension);
    }

    public function test_a_file_uploaded_without_a_translation_waits_with_a_token(): void
    {
        $response = $this->postJson(route('admin.blog-posts.files.upload'), [
            'file' => $this->upload('brosur.pdf'),
        ]);

        $response->assertOk();
        $this->assertNull($response->json('id'));
        $this->assertNotNull($response->json('token'));

        $file = BlogPostFile::sole();

        $this->assertNull($file->blog_post_id);
        $this->assertSame($this->admin->id, $file->user_id);
    }

    public function test_an_extension_outside_the_whitelist_is_refused(): void
    {
        $this->postJson(route('admin.blog-posts.files.upload'), [
            'file' => $this->upload('shell.php', '<?php echo 1;'),
        ])->assertStatus(422);

        // Çift uzantı da geçmiyor: son uzantı neyse o sayılıyor.
        $this->postJson(route('admin.blog-posts.files.upload'), [
            'file' => $this->upload('resim.jpg.php', 'x'),
        ])->assertStatus(422);

        $this->assertSame(0, BlogPostFile::count());
    }

    // ── Kaydetme ──

    public function test_a_pending_file_is_attached_to_the_row_its_language_creates(): void
    {
        $token = $this->postJson(route('admin.blog-posts.files.upload'), [
            'file' => $this->upload('kilavuz.pdf'),
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
        $file = BlogPostFile::sole();

        $this->assertSame($english->id, $file->blog_post_id);
        $this->assertNull($file->token);
    }

    public function test_a_pending_file_whose_language_never_got_a_row_is_discarded(): void
    {
        $token = $this->postJson(route('admin.blog-posts.files.upload'), [
            'file' => $this->upload('bosta.pdf'),
        ])->json('token');

        $path = BlogPostFile::sole()->path;

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

        $this->assertSame(0, BlogPostFile::withTrashed()->count());
        $this->assertFileDoesNotExist(UploadService::basePath($path));
    }

    public function test_a_pending_file_survives_a_validation_error(): void
    {
        $token = $this->postJson(route('admin.blog-posts.files.upload'), [
            'file' => $this->upload('kilavuz.pdf'),
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
            $this->postJson(route('admin.blog-posts.files.upload'), [
                'file'         => $this->upload($name),
                'blog_post_id' => $turkish->id,
            ])->assertOk();
        }

        $this->assertCount(3, $turkish->files()->get());
        $this->assertCount(0, $english->files()->get());
    }

    // ── Kaldırma ──

    public function test_removing_an_attachment_deletes_the_file_from_disk(): void
    {
        $post = $this->turkishPost();

        $this->postJson(route('admin.blog-posts.files.upload'), [
            'file'         => $this->upload('gecici.txt'),
            'blog_post_id' => $post->id,
        ])->assertOk();

        $file = BlogPostFile::sole();
        $path = UploadService::basePath($file->path);

        $this->assertFileExists($path);

        $this->deleteJson(route('admin.blog-posts.files.destroy', $file))->assertOk();

        $this->assertSame(0, BlogPostFile::withTrashed()->count());
        $this->assertFileDoesNotExist($path);
    }

    public function test_a_soft_deleted_post_hides_its_files_and_restoring_brings_them_back(): void
    {
        $post = $this->turkishPost();

        $this->postJson(route('admin.blog-posts.files.upload'), [
            'file'         => $this->upload('ek.pdf'),
            'blog_post_id' => $post->id,
        ])->assertOk();

        $path = UploadService::basePath(BlogPostFile::sole()->path);

        $post->delete();

        $this->assertSame(0, BlogPostFile::count());
        $this->assertSame(1, BlogPostFile::onlyTrashed()->count());
        // Yumuşak silme dosyaya dokunmuyor; geri alınabilir olması bunu gerektiriyor.
        $this->assertFileExists($path);

        $post->restore();

        $this->assertSame(1, BlogPostFile::count());
    }

    // ── Ön yüz ──

    public function test_the_front_serves_the_file_under_the_name_it_was_uploaded_with(): void
    {
        $post = $this->turkishPost();

        $this->postJson(route('admin.blog-posts.files.upload'), [
            'file'         => $this->upload('Satis Raporu 2026.csv', "ad;soyad\n"),
            'blog_post_id' => $post->id,
        ])->assertOk();

        $file = BlogPostFile::sole();

        // Diskteki ad eğik ve benzersiz; kullanıcıya kendi adıyla iniyor.
        $this->assertNotSame('Satis Raporu 2026.csv', basename($file->path));

        $this->get(route('blog.files.download', $file))
            ->assertOk()
            ->assertDownload('Satis Raporu 2026.csv');
    }

    public function test_a_file_of_an_unpublished_post_is_not_served(): void
    {
        $post = $this->turkishPost();
        $post->update(['status' => 'draft']);

        $this->postJson(route('admin.blog-posts.files.upload'), [
            'file'         => $this->upload('gizli.pdf'),
            'blog_post_id' => $post->id,
        ])->assertOk();

        $this->get(route('blog.files.download', BlogPostFile::sole()))->assertNotFound();
    }

    public function test_attachments_are_grouped_by_kind_in_a_fixed_order(): void
    {
        $post = $this->turkishPost();

        // Yükleme sırası bilerek karışık: gruplama sırası ailelerin kendi
        // sırası olmalı, yoksa aynı yazı her açılışta başka türlü dizilirdi.
        foreach (['tablo.xlsx', 'gorsel.png', 'belge.pdf', 'ikinci.png'] as $name) {
            $this->postJson(route('admin.blog-posts.files.upload'), [
                'file'         => $this->upload($name),
                'blog_post_id' => $post->id,
            ])->assertOk();
        }

        $grouped = app(BlogPostFileService::class)->groupByKind($post->files()->get());

        $this->assertSame(
            [FileKind::Image->value, FileKind::Pdf->value, FileKind::Spreadsheet->value],
            $grouped->keys()->all(),
        );
        $this->assertCount(2, $grouped[FileKind::Image->value]);
    }
}
