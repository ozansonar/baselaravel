<?php

declare(strict_types=1);

namespace App\Services\Concerns;

use App\Models\Language;
use App\Services\LanguageService;
use App\Services\UploadService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Saves a form that carries one block of fields per language.
 *
 * The admin forms post translations[{locale}][field], and every language block
 * becomes its own row sharing a lang_group_id. Because a translation is a whole
 * row, per-language files (an image with text baked into it) come along for
 * free — each block brings its own upload.
 *
 * A language block that is left empty is skipped rather than saved blank, so
 * content can be translated later without creating placeholder rows.
 */
trait SyncsTranslations
{
    /**
     * @param class-string<Model> $modelClass
     * @param array<string, array<string, mixed>> $translations locale => fields
     * @param callable(array<string, mixed>, string, Model|null, Model|null): array<string, mixed> $prepare
     *        Hook for per-row work such as uploads. Receives the fields, the
     *        locale, the existing row (null when creating) and the default
     *        language's row, which is what a new translation inherits files
     *        from until it has its own.
     */
    protected function saveTranslations(
        string $modelClass,
        array $translations,
        callable $prepare,
        ?string $groupId = null,
    ): string {
        $groupId ??= (string) Str::uuid();

        DB::transaction(function () use ($modelClass, $translations, $prepare, $groupId): void {
            foreach ($this->activeLocales() as $locale) {
                $fields = $translations[$locale] ?? null;

                /** @var Model|null $existing */
                $existing = $modelClass::query()
                    ->where('lang_group_id', $groupId)
                    ->where('locale', $locale)
                    ->first();

                if ($fields === null || $this->isEmptyBlock($fields)) {
                    // Nothing supplied: leave an existing translation untouched
                    // rather than wiping it, and do not create an empty one.
                    continue;
                }

                $defaultRow = $locale === $this->defaultLocale()
                    ? $existing
                    : $modelClass::query()
                        ->where('lang_group_id', $groupId)
                        ->where('locale', $this->defaultLocale())
                        ->first();

                $payload = $prepare($this->normalizeSettings($fields), $locale, $existing, $defaultRow);

                if ($payload === []) {
                    continue;
                }

                if ($existing !== null) {
                    $existing->update($payload);

                    continue;
                }

                $modelClass::create($payload + [
                    'locale'        => $locale,
                    'lang_group_id' => $groupId,
                ]);
            }
        });

        return $groupId;
    }

    /**
     * @return array<int, string>
     */
    protected function activeLocales(): array
    {
        return app(LanguageService::class)->activeCodes();
    }

    protected function defaultLocale(): string
    {
        return app(LanguageService::class)->defaultCode();
    }

    /**
     * Settings columns are NOT NULL with a database default, but an emptied
     * number input arrives as null and would break the insert. Normalising
     * here keeps every module safe rather than each form remembering to.
     *
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    protected function normalizeSettings(array $fields): array
    {
        if (array_key_exists('sort_order', $fields) && ($fields['sort_order'] === null || $fields['sort_order'] === '')) {
            $fields['sort_order'] = 0;
        }

        if (array_key_exists('is_active', $fields)) {
            $fields['is_active'] = (bool) $fields['is_active'];
        }

        return $fields;
    }

    /**
     * Keys that never say anything about whether a translation was written.
     *
     * A sort order defaults to 0, a visibility switch defaults to on and a
     * status select always carries a value, so every form posts them for every
     * language — including the tabs the translator never opened. Counting them
     * would turn an untouched tab into a row with a null title.
     *
     * @return list<string>
     */
    protected function nonContentKeys(): array
    {
        return ['locale', 'lang_group_id', 'id', 'sort_order', 'is_active', 'status', 'published_at'];
    }

    /**
     * A block counts as empty when the translator filled in nothing at all;
     * checkboxes and hidden defaults must not make it look filled.
     *
     * @param array<string, mixed> $fields
     */
    protected function isEmptyBlock(array $fields): bool
    {
        foreach ($fields as $key => $value) {
            if (in_array($key, $this->nonContentKeys(), true)) {
                continue;
            }

            if (is_array($value)) {
                if ($value !== []) {
                    return false;
                }

                continue;
            }

            if ($value instanceof \Illuminate\Http\UploadedFile) {
                return false;
            }

            if (is_string($value) && trim($value) !== '') {
                return false;
            }

            if (! is_string($value) && $value !== null && $value !== false && $value !== '0') {
                return false;
            }
        }

        return true;
    }

    /**
     * Görsel servisini çözer.
     *
     * Eskiden `$this->uploadService` okunuyordu — trait'in **bildirmediği**,
     * kullanan sınıfta var olduğu varsayılan bir özellik. Trait'i kullanan
     * sekiz servisin üçünde (blog kategorisi, SSS, galeri kategorisi) böyle
     * bir özellik yok; bugün o üçü görselli yolu hiç çağırmadığı için sorun
     * çıkmıyor, ama çağırdıkları gün ölümcül hata alırlardı. Trait artık
     * kendi bağımlılığını kendi çözüyor, kullanan sınıfın içine uzanmıyor.
     *
     * Servis durumsuz (kurucusu yok, yolları yapılandırmadan okuyor), yani
     * kaptan çözülen örnek enjekte edilenle aynı davranıyor.
     */
    private function uploads(): UploadService
    {
        return app(UploadService::class);
    }

    /**
     * Handle the one-image-per-language case every content form shares.
     *
     * A block with no new file keeps whatever that language already had, so
     * saving the Turkish tab never clears the English artwork.
     *
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    protected function prepareImageField(
        array $fields,
        ?Model $existing,
        string $folder,
        string $nameKey = 'title',
        string $imageKey = 'image',
        ?Model $inheritFrom = null,
    ): array {
        $image = $fields[$imageKey] ?? null;

        // Kaldırma isteği forma özel bir bayrakla geliyor ve sütun olmadığı
        // için modele ulaşmadan çıkarılıyor.
        $kaldir = filter_var($fields['remove_' . $imageKey] ?? false, FILTER_VALIDATE_BOOL);
        unset($fields['remove_' . $imageKey]);

        if (! $image instanceof \Illuminate\Http\UploadedFile) {
            unset($fields[$imageKey]);

            if ($kaldir) {
                // Dosya diskten de siliniyor; kayıt boşaltılıp dosya bırakılsa
                // uploads dizininde sahibi olmayan görseller birikirdi.
                if ($existing?->getAttribute($imageKey)) {
                    $this->uploads()->deleteImage((string) $existing->getAttribute($imageKey));
                }

                $fields[$imageKey] = null;

                // Kaldırma açıkça istendi: varsayılan dilin görseli de
                // devralınmıyor, yoksa kaldırdığı görsel geri gelmiş gibi olurdu.
                return $fields;
            }

            // A brand new translation with no artwork of its own borrows the
            // default language's, so the content still renders while the
            // translated artwork is being prepared.
            if ($existing === null && $inheritFrom?->getAttribute($imageKey)) {
                $fields[$imageKey] = $inheritFrom->getAttribute($imageKey);
            }

            return $fields;
        }

        $name = (string) ($fields[$nameKey] ?? $folder);
        $current = $existing?->getAttribute($imageKey);

        $fields[$imageKey] = $current
            ? $this->uploads()->replaceImage($image, $folder, $name, $current)
            : $this->uploads()->uploadImage($image, $folder, $name);

        return $fields;
    }

    /**
     * Languages the form should render a tab for, default language first.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Language>
     */
    public function formLanguages(): \Illuminate\Database\Eloquent\Collection
    {
        $languages = app(LanguageService::class)->active();

        return $languages->sortByDesc(fn (Language $language): bool => $language->is_default)->values();
    }
}
