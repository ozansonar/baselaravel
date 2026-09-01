<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Rules\EmailAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Ziyaretçiden alınan e-posta adresinin kuralı.
 *
 * Kural iki şeyi bir arada söylüyor: biçim doğru mu, ve alan adı gerçekten
 * posta alabiliyor mu (`dns`). İkincisi uydurma alan adlarıyla gelen form
 * spam'ini kaynağında eler, ama canlı bir DNS sorgusu demek — ve suite'i
 * üçüncü tarafların DNS kayıtlarına bağımlı kılıyordu.
 *
 * Nitekim bağladı: testlerin kullandığı `ornek.com` alan adının kayıtları bir
 * gün düştü ve kodda hiçbir şey değişmemişken beş test birden kırmızıya döndü.
 * Ağa çıkamayan bir makinede suite hiç geçmiyordu.
 *
 * Çözüm sınamada `dns`i düşürmek oldu. Bu muafiyetin kuralın sessizce
 * gevşetilmesine dönüşmemesi için üretim kuralını buradaki sınamalar
 * bekçiliyor.
 */
class EmailValidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Muafiyetin bedeli bu sınama: `dns` üretim kuralından düşerse kırılır.
     *
     * Sınama ortamında kuralı gevşetmenin tek meşru gerekçesi, üretimde sıkı
     * kalıyor olması.
     */
    /**
     * Biçim kuralının yettiği yerler ve gerekçeleri.
     *
     * Ortak nokta şu: buralarda adres **yeni** değil. Giriş ve şifre
     * sıfırlamada zaten kayıtlı bir hesabı gösteriyor — engellemek, kaydı bir
     * şekilde oluşmuş kişiyi kendi hesabından kilitlemek olurdu. Panelde ise
     * adresi giren yönetici: geçici bir hesabı bilerek açabilmeli, çünkü kural
     * bir yasak değil spam süzgeci ve yönetici spam değil.
     *
     * @var array<string, string>
     */
    private const FORMAT_ONLY = [
        'Http/Requests/Auth/ForgotPasswordRequest.php'   => 'Var olan hesabın adresi; yeni kayıt değil.',
        'Http/Requests/Auth/LoginRequest.php'            => 'Var olan hesabın adresi; yeni kayıt değil.',
        'Http/Requests/Api/V1/ForgotPasswordRequest.php' => 'Var olan hesabın adresi; yeni kayıt değil.',
        'Http/Requests/Api/V1/LoginRequest.php'          => 'Var olan hesabın adresi; yeni kayıt değil.',
        'Http/Requests/Api/V1/ResetPasswordRequest.php'  => 'Sıfırlama bağlantısındaki adres; yeni kayıt değil.',
        'Http/Requests/Admin/UpdateSubscriberRequest.php' => 'Panelden abone düzenleme: yöneticinin kararı.',
        'Http/Requests/Admin/UpdateProfileRequest.php'    => 'Panel kullanıcısının kendi hesabı; yeni kayıt değil.',
        'Http/Requests/StoreUserRequest.php'              => 'Panelden kullanıcı ekleme: yöneticinin kararı.',
        'Http/Requests/UpdateUserRequest.php'             => 'Panelden kullanıcı düzenleme: yöneticinin kararı.',
    ];

    public function test_the_production_rule_still_checks_the_domain(): void
    {
        $this->assertStringContainsString(
            'dns',
            EmailAddress::RULE,
            'Üretim kuralı alan adını denetlemeyi bırakmış: form spam\'i doğrudan içeri girer.',
        );
    }

    public function test_the_suite_itself_never_asks_dns(): void
    {
        $this->assertTrue(app()->runningUnitTests());

        $this->assertSame(
            EmailAddress::RULE_WITHOUT_DNS,
            EmailAddress::rule(),
            'Sınama ortamında kural hâlâ DNS soruyor: suite ağa bağımlı kalır.',
        );

        $this->assertStringNotContainsString('dns', EmailAddress::rule());
    }

    /**
     * Kural tek yerde durmalı.
     *
     * Beş ayrı istek sınıfında elle yazılıydı; biri değişip ötekiler geride
     * kalırdı — nitekim ön yüz kaydı ile panelden kullanıcı ekleme bugün bile
     * aynı şeyi sormuyor.
     */
    public function test_no_form_writes_the_rule_by_hand(): void
    {
        $offenders = [];

        foreach (File::allFiles(app_path()) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getRealPath();

            if ($path === realpath(app_path('Rules/EmailAddress.php'))) {
                continue;
            }

            // Yorumlar taramanın dışında: kuralı *anlatan* bir açıklama
            // (NotDisposableEmail'in başındaki gibi) kendi kuralına
            // takılmamalı. Aranan şey kodda yazılmış kural, metinde geçen
            // kural adı değil.
            if (str_contains($this->withoutComments((string) File::get($path)), 'email:rfc,dns')) {
                $offenders[] = str_replace(base_path() . '/', '', $path);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Kural elle yazılmış: " . implode(', ', $offenders) . ". App\\Rules\\EmailAddress::rule() kullanın.",
        );
    }

    /**
     * Ziyaretçiden **ilk kez** adres alan her form tam kural kümesini
     * kullanmalı.
     *
     * Kural yazılıydı ama bekçisi yoktu ve nitekim delinmişti: üyelik kaydı
     * (hem web hem API), hesap adresini değiştirme ve yorum formu düz `email`
     * kuralıyla yetiniyordu — ne alan adına ne de tek kullanımlık listesine
     * bakıyorlardı. Aynı sitede iletişim formu ikisini de yapıyordu.
     *
     * Kapsam elle yazılmış listeden değil dosya ağacından besleniyor: `email`
     * alanı doğrulayan her istek sınıfı sınava giriyor.
     */
    public function test_every_visitor_form_uses_the_full_rule_set(): void
    {
        $offenders = [];

        foreach ($this->requestClassesValidatingEmail() as $path => $contents) {
            if (isset(self::FORMAT_ONLY[$path])) {
                continue;
            }

            if (! str_contains($contents, 'EmailAddress::rules()')) {
                $offenders[] = $path;
            }
        }

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            "Ziyaretçiden adres alan form tam kural kümesini kullanmıyor —\n"
            . "`...EmailAddress::rules()` ekleyin ya da gerekçesiyle FORMAT_ONLY listesine yazın:\n  "
                . implode("\n  ", $offenders),
        );
    }

    /**
     * Biçim kuralı yeten yerler bilerek dışarıda — ve bu liste bayatlamamalı.
     *
     * İki yönü var: dosya silinmiş olabilir (satır ölü), ya da form sonradan
     * tam kural kümesine geçmiş olabilir (istisna yanlış yere bakıyor).
     */
    public function test_the_format_only_list_does_not_go_stale(): void
    {
        $classes = $this->requestClassesValidatingEmail();

        foreach (self::FORMAT_ONLY as $path => $reason) {
            $this->assertArrayHasKey(
                $path,
                $classes,
                "{$path} artık e-posta doğrulamıyor ya da silinmiş; listeden çıkarın.",
            );

            $this->assertStringNotContainsString(
                'EmailAddress::rules()',
                $classes[$path],
                "{$path} artık tam kural kümesini kullanıyor; listeden çıkarın.",
            );

            $this->assertNotSame('', trim($reason), "{$path} için gerekçe yazılmamış.");
        }
    }

    /**
     * Yorumları aynı uzunlukta boşlukla değiştirir — satır numaraları kaymasın.
     */
    private function withoutComments(string $code): string
    {
        $blank = static fn (array $m): string => (string) preg_replace('/[^\n]/', ' ', $m[0]);

        $code = (string) preg_replace_callback('#/\*.*?\*/#s', $blank, $code);

        return (string) preg_replace_callback('#//[^\n]*#', $blank, $code);
    }

    /**
     * `email` alanı doğrulayan istek sınıfları.
     *
     * @return array<string, string> yol => içerik
     */
    private function requestClassesValidatingEmail(): array
    {
        $found = [];

        foreach (File::allFiles(app_path('Http/Requests')) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) File::get($file->getRealPath());

            if (! preg_match("/'email'\s*=>\s*\[/", $contents)) {
                continue;
            }

            $found[str_replace(app_path() . '/', '', $file->getRealPath())] = $contents;
        }

        ksort($found);

        return $found;
    }

    // ── Davranış ──

    public function test_the_contact_form_still_refuses_a_malformed_address(): void
    {
        $this->post(route('contact.store', ['locale' => 'tr']), [
            'name'    => 'Ahmet Yilmaz',
            'email'   => 'bu-bir-adres-degil',
            'subject' => 'Konu',
            'message' => 'Yeterince uzun bir deneme mesaji.',
        ])->assertSessionHasErrors('email');
    }

    /**
     * Suite'i kıran senaryonun kendisi: biçimi doğru bir adres, alan adının o
     * anki DNS kayıtlarına bakılmaksızın kabul edilmeli.
     */
    public function test_a_well_formed_address_is_accepted_without_a_dns_lookup(): void
    {
        $this->post(route('contact.store', ['locale' => 'tr']), [
            'name'    => 'Ahmet Yilmaz',
            'email'   => 'a@ornek.com',
            'subject' => 'Konu',
            'message' => 'Yeterince uzun bir deneme mesaji.',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('contact_messages', ['email' => 'a@ornek.com']);
    }

    public function test_the_newsletter_form_behaves_the_same_way(): void
    {
        $this->postJson(route('newsletter.subscribe'), ['email' => 'bu-bir-adres-degil'])
            ->assertStatus(422);

        $this->postJson(route('newsletter.subscribe'), ['email' => 'yeni@ornek.com'])
            ->assertOk();

        $this->assertDatabaseHas('subscribers', ['email' => 'yeni@ornek.com']);
    }

    public function test_the_api_contact_form_behaves_the_same_way(): void
    {
        $this->postJson('/api/v1/contact', [
            'name'    => 'Ahmet Yilmaz',
            'email'   => 'bu-bir-adres-degil',
            'subject' => 'Konu',
            'message' => 'Yeterince uzun bir deneme mesaji.',
        ])->assertStatus(422);

        $this->postJson('/api/v1/contact', [
            'name'    => 'Ahmet Yilmaz',
            'email'   => 'a@ornek.com',
            'subject' => 'Konu',
            'message' => 'Yeterince uzun bir deneme mesaji.',
        ])->assertCreated();
    }

    // ── Tek kullanımlık adresler ──

    /**
     * Kural üç biçimi de yakalamalı: tam eşleşme, alt alan adı ve büyük harf.
     *
     * Alt alan adı önemli: sağlayıcılar genelde her kullanıcıya bir alt alan
     * adı veriyor ve yalnız tam eşleşmeye bakan bir liste ilk gün delinirdi.
     */
    #[DataProvider('disposableAddresses')]
    public function test_a_disposable_address_is_refused(string $address): void
    {
        $this->post(route('contact.store', ['locale' => 'tr']), [
            'name'    => 'Ahmet Yilmaz',
            'email'   => $address,
            'subject' => 'Konu',
            'message' => 'Yeterince uzun bir deneme mesaji.',
        ])->assertSessionHasErrors('email');
    }

    /** @return array<string, array{0: string}> */
    public static function disposableAddresses(): array
    {
        return [
            'tam eşleşme'   => ['deneme@10minutemail.com'],
            'alt alan adı'  => ['deneme@kutu.10minutemail.com'],
            'büyük harf'    => ['deneme@YOPMAIL.COM'],
        ];
    }

    /**
     * Üyelik kaydı — bu boşluğun asıl bulunduğu yer. Kayıt formu düz `email`
     * kuralıyla yetiniyordu: ne alan adına ne de tek kullanımlık listesine
     * bakıyordu.
     */
    public function test_registration_refuses_a_disposable_address(): void
    {
        $this->post('/tr/kayit', [
            'first_name'            => 'Ahmet',
            'last_name'             => 'Yilmaz',
            'email'                 => 'deneme@10minutemail.com',
            'password'              => 'Gizli*12345',
            'password_confirmation' => 'Gizli*12345',
            'terms'                 => '1',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('users', ['email' => 'deneme@10minutemail.com']);
    }

    public function test_the_api_registration_refuses_it_too(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'first_name'            => 'Ahmet',
            'last_name'             => 'Yilmaz',
            'email'                 => 'deneme@10minutemail.com',
            'password'              => 'Gizli*12345',
            'password_confirmation' => 'Gizli*12345',
            'device_name'           => 'deneme',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_the_newsletter_refuses_a_disposable_address(): void
    {
        $this->post(route('newsletter.subscribe', ['locale' => 'tr']), [
            'email' => 'deneme@10minutemail.com',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('subscribers', ['email' => 'deneme@10minutemail.com']);
    }

    public function test_the_api_newsletter_refuses_it_too(): void
    {
        $this->postJson('/api/v1/newsletter/subscribe', [
            'email' => 'deneme@10minutemail.com',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    /**
     * Kalıcı bir adres hiçbir yerde engellenmemeli — süzgeç spam'i eliyor,
     * ziyaretçiyi değil.
     */
    public function test_a_permanent_address_still_passes_everywhere(): void
    {
        $this->post(route('newsletter.subscribe', ['locale' => 'tr']), [
            'email' => 'gercek@ornek.com',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('subscribers', ['email' => 'gercek@ornek.com']);
    }

    /**
     * Panelde engel yok: yönetici geçici bir hesabı bilerek açabilmeli.
     * Kural bir yasak değil, spam süzgeci — ve yönetici spam değil.
     */
    public function test_the_admin_panel_is_not_blocked(): void
    {
        $rules = (new \App\Http\Requests\StoreUserRequest())->rules();

        $this->assertStringNotContainsString(
            'NotDisposable',
            json_encode(array_map(
                static fn ($r) => is_array($r) ? array_map(static fn ($x) => is_object($x) ? $x::class : $x, $r) : $r,
                $rules,
            ), JSON_UNESCAPED_UNICODE) ?: '',
        );
    }
}
