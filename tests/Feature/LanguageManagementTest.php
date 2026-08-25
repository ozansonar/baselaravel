<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Language;
use App\Services\LanguageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The language list is panel-managed, and exactly one language is the default.
 *
 * Everything downstream leans on that invariant: the locale middleware falls
 * back to the default, the content tabs are built from the active list, and the
 * front-end switcher shows it.
 */
class LanguageManagementTest extends TestCase
{
    use RefreshDatabase;

    private LanguageService $languages;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        $this->languages = app(LanguageService::class);
        $this->languages->clearCache();
    }

    public function test_the_seeded_list_has_one_default(): void
    {
        $this->assertSame(1, Language::where('is_default', true)->count());
        $this->assertSame('tr', $this->languages->defaultCode());
    }

    public function test_only_active_languages_are_offered(): void
    {
        $this->assertSame(['tr', 'en'], $this->languages->activeCodes());

        $this->assertTrue($this->languages->isSupported('en'));
        $this->assertFalse($this->languages->isSupported('de'), 'Pasif dil destekleniyor görünüyor');
        $this->assertFalse($this->languages->isSupported('xx'));
        $this->assertFalse($this->languages->isSupported(null));
    }

    public function test_making_a_language_default_clears_the_previous_one(): void
    {
        $english = Language::where('code', 'en')->firstOrFail();

        $this->languages->makeDefault($english);

        $this->assertSame(1, Language::where('is_default', true)->count(), 'İkinci varsayılan oluştu');
        $this->assertSame('en', $this->languages->defaultCode());
        $this->assertFalse(Language::where('code', 'tr')->firstOrFail()->is_default);
    }

    public function test_making_an_inactive_language_default_also_activates_it(): void
    {
        $german = Language::where('code', 'de')->firstOrFail();
        $this->assertFalse($german->is_active);

        $this->languages->makeDefault($german);

        $fresh = $german->fresh();
        $this->assertTrue($fresh->is_default);
        $this->assertTrue($fresh->is_active, 'Varsayılan dil pasif kalamaz');
    }

    public function test_the_default_language_cannot_be_switched_off(): void
    {
        $turkish = Language::where('code', 'tr')->firstOrFail();

        $this->languages->update($turkish, [
            'code'      => 'tr',
            'name'      => 'Türkçe',
            'is_active' => false,
        ]);

        $this->assertTrue($turkish->fresh()->is_active, 'Varsayılan dil kapatılabildi');
    }

    public function test_the_default_language_cannot_be_deleted(): void
    {
        $turkish = Language::where('code', 'tr')->firstOrFail();

        $result = $this->languages->delete($turkish);

        $this->assertFalse($result['deleted']);
        $this->assertNotSoftDeleted('languages', ['id' => $turkish->id]);
    }

    public function test_a_non_default_language_can_be_deleted(): void
    {
        $italian = Language::where('code', 'it')->firstOrFail();

        $result = $this->languages->delete($italian);

        $this->assertTrue($result['deleted']);
        $this->assertSoftDeleted('languages', ['id' => $italian->id]);
    }

    public function test_the_last_language_cannot_be_deleted(): void
    {
        Language::where('code', '!=', 'tr')->forceDelete();

        $result = $this->languages->delete(Language::where('code', 'tr')->firstOrFail());

        $this->assertFalse($result['deleted']);
        $this->assertSame(1, Language::count());
    }

    public function test_the_first_language_added_becomes_the_default(): void
    {
        Language::query()->forceDelete();
        $this->languages->clearCache();

        $created = $this->languages->create([
            'code' => 'es',
            'name' => 'İspanyolca',
        ]);

        $this->assertTrue($created->fresh()->is_default, 'İlk dil varsayılan olmadı');
    }

    public function test_a_later_language_does_not_steal_the_default(): void
    {
        $created = $this->languages->create([
            'code' => 'pt',
            'name' => 'Portekizce',
        ]);

        $this->assertFalse($created->fresh()->is_default);
        $this->assertSame('tr', $this->languages->defaultCode());
        $this->assertSame(1, Language::where('is_default', true)->count());
    }

    public function test_language_codes_are_unique(): void
    {
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        Language::create(['code' => 'tr', 'name' => 'Kopya']);
    }

    public function test_the_label_shows_the_flag_and_native_name(): void
    {
        $turkish = Language::where('code', 'tr')->firstOrFail();

        $this->assertSame('🇹🇷 Türkçe', $turkish->label());
    }
}
