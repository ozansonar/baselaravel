<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Faq;
use App\Models\Setting;
use App\Services\LanguageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Seyrek değişen uçların önbelleklenmesi.
 *
 * Çeviri sözlüğü yüz kilobayta yaklaşabiliyor ve uygulama her açılışta onu
 * baştan indiriyordu. ETag ile içerik değişmemişse gövde hiç inmiyor.
 *
 * Buradaki en kritik sınama `Vary`: aynı adres dile göre farklı içerik
 * döndürüyor ve bu bildirilmezse araya giren her önbellek ilk gelenin dilini
 * ötekilere de servis eder — ETag'lerle birlikte yanlış dil kalıcı olarak
 * saklanır.
 */
class ApiCachingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        app(LanguageService::class)->clearCache();
    }

    public function test_a_reference_endpoint_carries_an_etag(): void
    {
        $response = $this->getJson('/api/v1/languages')->assertOk();

        $this->assertNotEmpty($response->headers->get('ETag'));
        $this->assertStringContainsString('max-age=' . config('api.cache.max_age'), (string) $response->headers->get('Cache-Control'));
    }

    public function test_an_unchanged_endpoint_answers_304_with_no_body(): void
    {
        $etag = $this->getJson('/api/v1/languages')->assertOk()->headers->get('ETag');

        $second = $this->withHeader('If-None-Match', (string) $etag)
            ->getJson('/api/v1/languages')
            ->assertStatus(304);

        // Kazanç tam olarak burada: gövde hiç inmiyor.
        $this->assertSame('', $second->getContent());
    }

    public function test_changed_content_gets_a_new_etag(): void
    {
        Setting::setValue('site_name', 'Eski Ad', 'general');
        $first = $this->getJson('/api/v1/settings')->assertOk()->headers->get('ETag');

        Setting::setValue('site_name', 'Yeni Ad', 'general');
        $second = $this->getJson('/api/v1/settings')->assertOk();

        $this->assertNotSame($first, $second->headers->get('ETag'));

        // Eski etiketle gelen istemci 304 değil taze içerik almalı.
        $this->withHeader('If-None-Match', (string) $first)
            ->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.general.site_name', 'Yeni Ad');
    }

    /**
     * Vary olmadan, Türkçe cevabı önbelleğe alan bir vekil aynı ETag'i
     * İngilizce isteyene de verirdi.
     */
    public function test_the_response_declares_that_it_varies_by_language(): void
    {
        $vary = (string) $this->getJson('/api/v1/languages')->assertOk()->headers->get('Vary');

        $this->assertStringContainsString('Accept-Language', $vary);
        $this->assertStringContainsString('X-Locale', $vary);
    }

    public function test_two_languages_do_not_share_an_etag(): void
    {
        Faq::factory()->create(['is_active' => true, 'question' => 'Nasıl üye olurum?', 'locale' => 'tr']);

        $turkish = $this->withHeader('Accept-Language', 'tr')->getJson('/api/v1/faqs')->assertOk();
        $english = $this->withHeader('Accept-Language', 'en')->getJson('/api/v1/translations')->assertOk();

        $this->assertNotSame($turkish->headers->get('ETag'), $english->headers->get('ETag'));

        // Türkçe etiketiyle İngilizce istemek 304 vermemeli.
        $this->withHeader('Accept-Language', 'en')
            ->withHeader('If-None-Match', (string) $turkish->headers->get('ETag'))
            ->getJson('/api/v1/translations')
            ->assertOk();
    }

    /**
     * İçerik listeleri bilerek önbelleklenmiyor: orada tazelik önbellekten
     * değerli ve sayfalama ETag'i zaten sürekli değiştiriyor.
     */
    public function test_content_lists_are_not_cached(): void
    {
        foreach (['/api/v1/blog/posts', '/api/v1/gallery', '/api/v1/home'] as $path) {
            $response = $this->getJson($path)->assertOk();

            $this->assertEmpty(
                $response->headers->get('ETag'),
                "{$path} önbelleklenmemeli — tazelik daha değerli.",
            );
        }
    }

    /**
     * Hata yanıtı önbelleklenmemeli: bir anlık 404, istemcinin elinde
     * dakikalarca kalıcı bir 404 hâline gelirdi.
     */
    public function test_errors_are_not_cached(): void
    {
        $response = $this->getJson('/api/v1/menus/yok-boyle-bir-konum')->assertNotFound();

        $this->assertEmpty($response->headers->get('ETag'));
    }
}
