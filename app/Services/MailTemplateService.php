<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MailTemplate;
use App\Support\MailTemplateDefaults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use App\Support\LikeSearch;

final class MailTemplateService
{
    private const CACHE_KEY = 'mail_templates.all';
    private const CACHE_TTL = 86400; // 24 hours

    /**
     * Süzülmüş ve sıralanmış şablon listesi.
     *
     * Altı kayıtlık bir tablo için sayfalama yok; süzgeçler yine de sunucuda
     * çalışıyor ki bağlantı paylaşılabilsin ve panelin diğer listeleriyle aynı
     * davranış (rozetler, sıfırlama, boş durum) elde edilsin.
     *
     * @param array<string, mixed> $filters
     * @return Collection<int, MailTemplate>
     */
    /**
     * Liste ekranının tanıdığı süzgeç anahtarları.
     *
     * @return list<string>
     */
    public function filterKeys(): array
    {
        return ['locale', 'status', 'search', 'variable', 'origin', 'sort'];
    }

    /**
     * Sorguya çevrilebilen süzgeçler.
     *
     * Değişken ve köken süzgeçleri burada yok: biri JSON sütununun içine, öbürü
     * varsayılan içerikle karşılaştırmaya bakıyor — ikisi de filter() içinde,
     * koleksiyon üzerinde uygulanıyor.
     *
     * @param array<string, mixed> $filters
     * @return Builder<MailTemplate>
     */
    public function query(array $filters = []): Builder
    {
        $query = MailTemplate::query();

        // Aynı şablonun her dilde bir satırı var. Süzgeç boşken hepsi
        // listelenirse ekranda aynı ad beş kez görünür; ekran hangi dile
        // baktığını her zaman söylüyor.
        if (($filters['locale'] ?? '') !== '') {
            $query->where('locale', $filters['locale']);
        }

        if (($filters['status'] ?? '') !== '') {
            $query->where('is_active', $filters['status'] === 'active');
        }

        if (($filters['search'] ?? '') !== '') {
            $term = $this->likeTerm((string) $filters['search']);

            $query->where(function (Builder $sub) use ($term): void {
                $sub->whereRaw(LikeSearch::clause('name'), [$term])
                    ->orWhereRaw(LikeSearch::clause('`key`'), [$term])
                    ->orWhereRaw(LikeSearch::clause('description'), [$term])
                    ->orWhereRaw(LikeSearch::clause('subject'), [$term]);
            });
        }

        return match ($filters['sort'] ?? '') {
            'recent' => $query->orderByDesc('updated_at'),
            'key'    => $query->orderBy('key'),
            default  => $query->orderBy('name'),
        };
    }

    public function filter(array $filters): Collection
    {
        $templates = $this->query($filters)->get();

        // Değişkenler JSON sütununda; altı satır için sorguyu veritabanına
        // özel JSON işlevlerine bağlamaktansa koleksiyonda süzmek yeterli.
        if (($filters['variable'] ?? '') !== '') {
            $templates = $templates->filter(
                fn (MailTemplate $template): bool => in_array($filters['variable'], $template->variableKeys(), true),
            );
        }

        if (($filters['origin'] ?? '') !== '') {
            $wantsCustomized = $filters['origin'] === 'customized';

            $templates = $templates->filter(
                fn (MailTemplate $template): bool => $this->isCustomized($template) === $wantsCustomized,
            );
        }

        return $templates->values();
    }

    /**
     * Şablonun varsayılan içeriği biliniyor mu?
     *
     * Bilinmiyorsa "varsayılana dön" da yapılamaz; ekran bu durumda ne
     * "özelleştirildi" ne de "varsayılan" demeli.
     */
    public function hasDefault(MailTemplate $template): bool
    {
        return $this->defaultFor($template) !== null;
    }

    /**
     * Şablon varsayılandan farklı mı?
     *
     * Karşılaştırma boşluklara duyarsız: kurulum sırasında yazılan içerik ile
     * buradaki varsayılan aynı metni farklı girintilerle tutuyor, satır başları
     * yüzünden her şablon "özelleştirilmiş" görünmemeli.
     */
    public function isCustomized(MailTemplate $template): bool
    {
        $default = $this->defaultFor($template);

        if ($default === null) {
            return false;
        }

        return $this->normalize($template->subject) !== $this->normalize($default['subject'])
            || $this->normalize($template->body) !== $this->normalize($default['body']);
    }

    /**
     * Süzgeçteki değişken listesi — kaç şablonda geçtiğiyle birlikte.
     *
     * @return array<string, array{label: string, count: int}> değişken => bilgi
     */
    public function variableOptions(?string $locale = null): array
    {
        $options = [];

        foreach ($this->scoped($locale)->get() as $template) {
            foreach ($template->variables ?? [] as $variable) {
                $key = (string) ($variable['key'] ?? '');

                if ($key === '') {
                    continue;
                }

                $options[$key] ??= ['label' => (string) ($variable['label'] ?? $key), 'count' => 0];
                $options[$key]['count']++;
            }
        }

        uasort($options, static fn (array $a, array $b): int => $b['count'] <=> $a['count'] ?: strcmp($a['label'], $b['label']));

        return $options;
    }

    /**
     * Özet kutuları.
     *
     * @return array{total: int, active: int, inactive: int, customized: int}
     */
    public function stats(?string $locale = null): array
    {
        $templates = $this->scoped($locale)->get();

        return [
            'total'      => $templates->count(),
            'active'     => $templates->where('is_active', true)->count(),
            'inactive'   => $templates->where('is_active', false)->count(),
            'customized' => $templates->filter(fn (MailTemplate $t): bool => $this->isCustomized($t))->count(),
        ];
    }

    /**
     * Bir dile daraltılmış sorgu.
     *
     * Sayılar dile bağlı olmalı: beş dilin satırları birlikte sayılınca özet
     * kutusu "60 şablon" diyor, ekranda ise 12 kart duruyordu.
     *
     * @return Builder<MailTemplate>
     */
    private function scoped(?string $locale): Builder
    {
        $query = MailTemplate::query();

        return $locale === null || $locale === ''
            ? $query
            : $query->where('locale', $locale);
    }

    /**
     * Aynı şablonun öteki dillerdeki satırları, dil sırasına göre.
     *
     * @return Collection<int, MailTemplate>
     */
    public function siblings(MailTemplate $template): Collection
    {
        $order = app(LanguageService::class)->all()->pluck('code')->values()->all();

        return MailTemplate::query()
            ->where('key', $template->key)
            ->get()
            ->sortBy(static function (MailTemplate $row) use ($order): int {
                $index = array_search($row->locale, $order, true);

                return $index === false ? PHP_INT_MAX : $index;
            })
            ->values();
    }

    /**
     * Arama terimini LIKE kalıbına çevirir; % ve _ joker değil harf sayılır.
     */
    private function likeTerm(string $value): string
    {
        return LikeSearch::term($value);
    }

    /**
     * Karşılaştırma için içeriği sadeleştirir.
     *
     * HTML'de peş peşe gelen boşluklar, satır başları ve etiketlerin hemen
     * içindeki/dışındaki boşluk okunan metni değiştirmiyor. Kurulumda yazılan
     * içerik ile buradaki varsayılan aynı metni farklı girintilerle tuttuğu
     * için bunlar temizlenmeden her şablon "özelleştirilmiş" görünürdü.
     */
    private function normalize(string $value): string
    {
        $value = (string) preg_replace('/\s+/', ' ', $value);
        $value = (string) preg_replace('/>\s+/', '>', $value);
        $value = (string) preg_replace('/\s+</', '<', $value);

        return trim($value);
    }

    /**
     * Find a template by ID.
     */
    public function findOrFail(int $id): MailTemplate
    {
        return MailTemplate::findOrFail($id);
    }

    /**
     * Update a template.
     */
    public function update(MailTemplate $template, array $data): MailTemplate
    {
        $template->update($data);
        $this->clearCache();

        return $template;
    }

    /**
     * Reset a template to its default content.
     */
    public function resetToDefault(MailTemplate $template): MailTemplate
    {
        $default = $this->defaultFor($template);

        if ($default !== null) {
            $template->update([
                'subject' => $default['subject'],
                'body'    => $default['body'],
            ]);
            $this->clearCache();
        }

        return $template;
    }

    /**
     * Bir dilde eksik kalan şablon satırlarını açar.
     *
     * Yeni bir dil eklendiğinde çağrılıyor: o dilin satırı olmadan panelde
     * çevrilecek bir şey görünmüyor, mail de varsayılan dile düşüyordu.
     * Zaten var olan satırlara dokunulmuyor — yöneticinin yazdığı metni
     * varsayılana geri çevirmek olurdu.
     *
     * @return int açılan satır sayısı
     */
    public function syncLocale(string $locale): int
    {
        $defaultLocale = app(LanguageService::class)->defaultCode();

        if ($locale === $defaultLocale) {
            return 0;
        }

        $existing = MailTemplate::query()->where('locale', $locale)->pluck('key')->all();
        $content = MailTemplateDefaults::forLocale($locale);
        $created = 0;

        foreach (MailTemplate::query()->where('locale', $defaultLocale)->get() as $source) {
            if (in_array($source->key, $existing, true)) {
                continue;
            }

            MailTemplate::create([
                'key'    => $source->key,
                'locale' => $locale,
                // Ad, açıklama ve değişken listesi şablonu panelde etiketliyor;
                // panel tek dilli, o yüzden diller arasında aynı kalıyor.
                'name'        => $source->name,
                'description' => $source->description,
                'subject'     => $content[$source->key]['subject'] ?? $source->subject,
                'body'        => $content[$source->key]['body'] ?? $source->body,
                'variables'   => $source->variables,
                'is_active'   => $source->is_active,
            ]);

            $created++;
        }

        if ($created > 0) {
            $this->clearCache();
        }

        return $created;
    }

    /**
     * Şablonun kendi dilindeki varsayılan içeriği.
     *
     * O dil için bir karşılık yoksa null: "varsayılana dön" düğmesi de,
     * "özelleştirildi" rozeti de karşılaştıracak bir metin olmadan yalan
     * söylerdi. Varsayılan dilin metnine düşmek, İngilizce bir şablonu
     * Türkçeye çevirmek anlamına gelirdi.
     *
     * @return array{subject: string, body: string}|null
     */
    private function defaultFor(MailTemplate $template): ?array
    {
        return MailTemplateDefaults::for($template->key, (string) $template->locale);
    }

    /**
     * Clear template cache.
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
