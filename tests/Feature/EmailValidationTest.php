<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Rules\EmailAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
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

            if (str_contains((string) File::get($path), 'email:rfc,dns')) {
                $offenders[] = str_replace(base_path() . '/', '', $path);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Kural elle yazılmış: " . implode(', ', $offenders) . ". App\\Rules\\EmailAddress::rule() kullanın.",
        );
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
}
