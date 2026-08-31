<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Services\LanguageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * API'nin sözleşmesi: yanıt zarfı, hata biçimi ve dil çözümü.
 *
 * Bu üçü mobil uygulamanın kod üreterek bağlandığı yüzey. Biri sessizce
 * değişirse uygulama mağazadan güncellenene kadar kırık kalır — o yüzden
 * ayrı bir sınama dosyası.
 */
class ApiContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        app(LanguageService::class)->clearCache();
    }

    // ── Zarf ──

    public function test_a_successful_response_carries_the_standard_envelope(): void
    {
        $response = $this->getJson('/api/v1/languages')->assertOk();

        $response->assertJsonStructure(['success', 'message', 'data'])
            ->assertJsonPath('success', true);

        $this->assertIsString($response->json('message'));
    }

    public function test_an_error_response_carries_the_standard_envelope(): void
    {
        $response = $this->getJson('/api/v1/menus/yok')->assertNotFound();

        $response->assertJsonStructure(['success', 'message', 'errors'])
            ->assertJsonPath('success', false);

        // `errors` doluyken nesne, boşken de nesne olmalı: PHP'de boş dizi
        // JSON'a `[]` diye iner ve istemci aynı alanı iki ayrı tipte görür —
        // tipli bir modele ayrıştırmak imkânsız hâle gelir. Ham gövdeye
        // bakılıyor: json() çıktısı çözülmüş olduğu için farkı göstermez.
        $this->assertStringContainsString('"errors":{}', (string) $response->getContent());
    }

    public function test_validation_errors_are_reported_per_field(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'gecersiz'])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'errors' => ['email', 'password']]);
    }

    /**
     * routes/web.php'deki fallback bilinmeyen adresleri varsayılan dile
     * yönlendiriyor — bir ziyaretçi için doğru, bir mobil istemci için değil:
     * beklediği JSON yerine 302 ve HTML alırdı.
     */
    public function test_an_unknown_api_path_returns_json_not_a_redirect(): void
    {
        $this->getJson('/api/v1/boyle-bir-uc-yok')
            ->assertNotFound()
            ->assertHeader('content-type', 'application/json')
            ->assertJsonPath('success', false);
    }

    /**
     * Yanlış yöntem 405 değil 404 dönüyor: yakalayıcı rota (fallback) adresi
     * kendi üstüne alıyor ve çerçevenin "başka bir yöntemle var mı" denetimine
     * hiç sıra gelmiyor. Ön yüzde de aynı — önemli olan istemcinin JSON alması
     * ve zarfın bozulmaması.
     */
    public function test_a_wrong_http_method_still_answers_with_the_envelope(): void
    {
        $this->getJson('/api/v1/contact')
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'errors']);
    }

    /**
     * `Accept` başlığı göndermeyi unutan istemci bile JSON almalı; aksi hâlde
     * elinde ayrıştıramadığı bir HTML hata sayfası kalıyor ve asıl hata
     * görünmez oluyor.
     */
    public function test_a_client_without_an_accept_header_still_gets_json(): void
    {
        $this->get('/api/v1/boyle-bir-uc-yok', ['Accept' => 'text/html'])
            ->assertNotFound()
            ->assertHeader('content-type', 'application/json');
    }

    public function test_security_headers_are_present_on_api_responses(): void
    {
        $this->getJson('/api/v1/languages')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    // ── CORS ──

    /**
     * Mobil uygulama CORS'a tabi değil (Origin göndermez); bu başlıklar harici
     * web ön yüzleri için. Ön uçuş (preflight) yanıtı gelmezse tarayıcı asıl
     * isteği hiç göndermez ve hata konsolda kalır — sunucu tarafında hiçbir iz
     * bırakmaz.
     */
    public function test_a_cors_preflight_is_answered(): void
    {
        $response = $this->call('OPTIONS', '/api/v1/languages', [], [], [], [
            'HTTP_ORIGIN'                         => 'https://uygulama.ornek.com',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD'  => 'GET',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,content-type',
        ]);

        $response->assertNoContent(204)
            ->assertHeader('Access-Control-Allow-Origin', '*');
    }

    public function test_cross_origin_responses_carry_the_allow_origin_header(): void
    {
        $this->withHeader('Origin', 'https://uygulama.ornek.com')
            ->getJson('/api/v1/languages')
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', '*');
    }

    // ── Dil ──

    public function test_accept_language_selects_the_response_language(): void
    {
        $this->withHeader('Accept-Language', 'en')
            ->getJson('/api/v1/languages')
            ->assertOk()
            ->assertHeader('Content-Language', 'en')
            ->assertJsonPath('meta.current', 'en');

        $this->withHeader('Accept-Language', 'tr')
            ->getJson('/api/v1/languages')
            ->assertOk()
            ->assertHeader('Content-Language', 'tr')
            ->assertJsonPath('meta.current', 'tr');
    }

    /**
     * "de-DE" gibi bölgesel bir etiket sitedeki "de" ile eşleşmeli; q değerleri
     * de sıralamayı belirlemeli. Mantık ön yüzle ortak
     * ({@see \App\Services\LocaleResolver::fromBrowser()}).
     */
    public function test_regional_tags_and_quality_values_are_honoured(): void
    {
        $this->withHeader('Accept-Language', 'en-GB,en;q=0.9,tr;q=0.8')
            ->getJson('/api/v1/languages')
            ->assertHeader('Content-Language', 'en');

        $this->withHeader('Accept-Language', 'fr;q=0.9,tr;q=1.0')
            ->getJson('/api/v1/languages')
            ->assertHeader('Content-Language', 'tr');
    }

    public function test_an_explicit_choice_beats_the_device_language(): void
    {
        // Uygulama içi dil menüsü cihazın dilini geçmeli.
        $this->withHeader('Accept-Language', 'en')
            ->getJson('/api/v1/languages?lang=tr')
            ->assertHeader('Content-Language', 'tr');

        $this->withHeader('Accept-Language', 'en')
            ->withHeader('X-Locale', 'tr')
            ->getJson('/api/v1/languages')
            ->assertHeader('Content-Language', 'tr');
    }

    /**
     * Sitede olmayan bir dil hata değil: mobil uygulama cihazın dilini
     * gönderiyor ve kullanıcı 404 değil, varsayılan dilde içerik görmeli.
     */
    public function test_an_unsupported_language_falls_back_instead_of_failing(): void
    {
        $this->withHeader('Accept-Language', 'ja')
            ->getJson('/api/v1/languages')
            ->assertOk()
            ->assertHeader('Content-Language', app(LanguageService::class)->defaultCode());

        $this->getJson('/api/v1/languages?lang=ja')
            ->assertOk();
    }

    public function test_error_messages_follow_the_requested_language(): void
    {
        $turkish = $this->withHeader('Accept-Language', 'tr')
            ->getJson('/api/v1/menus/yok')
            ->assertNotFound()
            ->json('message');

        $english = $this->withHeader('Accept-Language', 'en')
            ->getJson('/api/v1/menus/yok')
            ->assertNotFound()
            ->json('message');

        $this->assertNotSame($turkish, $english, 'Hata metni istenen dile göre değişmeli.');
        $this->assertSame(__('api.menus.not_found', [], 'tr'), $turkish);
        $this->assertSame(__('api.menus.not_found', [], 'en'), $english);
    }

    public function test_validation_messages_follow_the_requested_language(): void
    {
        $turkish = $this->withHeader('Accept-Language', 'tr')
            ->postJson('/api/v1/auth/login', ['email' => '', 'password' => ''])
            ->assertStatus(422)
            ->json('errors.email.0');

        $english = $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/auth/login', ['email' => '', 'password' => ''])
            ->assertStatus(422)
            ->json('errors.email.0');

        $this->assertNotSame($turkish, $english);
    }

    // ── Ön yüz etkilenmedi mi ──

    /**
     * API katmanı eklenirken ön yüzün ve panelin davranışı değişmemeli:
     * hata biçimlendiricisi yalnız /api/v1 altına bakıyor.
     */
    public function test_the_front_end_still_answers_with_html(): void
    {
        $response = $this->get('/tr');

        $this->assertStringContainsString('text/html', (string) $response->headers->get('content-type'));
    }
}
