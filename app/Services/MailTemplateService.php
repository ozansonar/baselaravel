<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MailTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class MailTemplateService
{
    private const CACHE_KEY = 'mail_templates.all';
    private const CACHE_TTL = 86400; // 24 hours

    /**
     * Get all templates for admin listing.
     *
     * @return EloquentCollection<int, MailTemplate>
     */
    public function getAll(): EloquentCollection
    {
        return MailTemplate::orderBy('name')->get();
    }

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
        return ['status', 'search', 'variable', 'origin', 'sort'];
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

        if (($filters['status'] ?? '') !== '') {
            $query->where('is_active', $filters['status'] === 'active');
        }

        if (($filters['search'] ?? '') !== '') {
            $term = $this->likeTerm((string) $filters['search']);

            $query->where(function (Builder $sub) use ($term): void {
                $sub->whereRaw("name LIKE ? ESCAPE '!'", [$term])
                    ->orWhereRaw("`key` LIKE ? ESCAPE '!'", [$term])
                    ->orWhereRaw("description LIKE ? ESCAPE '!'", [$term])
                    ->orWhereRaw("subject LIKE ? ESCAPE '!'", [$term]);
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
        return isset($this->getDefaults()[$template->key]);
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
        $default = $this->getDefaults()[$template->key] ?? null;

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
    public function variableOptions(): array
    {
        $options = [];

        foreach (MailTemplate::query()->get() as $template) {
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
    public function stats(): array
    {
        $templates = MailTemplate::query()->get();

        return [
            'total'      => $templates->count(),
            'active'     => $templates->where('is_active', true)->count(),
            'inactive'   => $templates->where('is_active', false)->count(),
            'customized' => $templates->filter(fn (MailTemplate $t): bool => $this->isCustomized($t))->count(),
        ];
    }

    /**
     * Arama terimini LIKE kalıbına çevirir; % ve _ joker değil harf sayılır.
     */
    private function likeTerm(string $value): string
    {
        return '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value) . '%';
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
        $defaults = $this->getDefaults();

        if (isset($defaults[$template->key])) {
            $template->update([
                'subject' => $defaults[$template->key]['subject'],
                'body'    => $defaults[$template->key]['body'],
            ]);
            $this->clearCache();
        }

        return $template;
    }

    /**
     * Clear template cache.
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Get default template contents (for reset functionality).
     *
     * @return array<string, array{subject: string, body: string}>
     */
    private function getDefaults(): array
    {
        return [
            'test' => [
                'subject' => '{site_name} — Test E-postası',
                'body'    => '<p class="em-greeting">Test E-postası</p>
<h1 class="em-heading">{mail_subject}</h1>

<p class="em-text">{mail_body}</p>

<hr class="em-divider">

<p class="em-text-sm">Bu e-posta, SMTP ayarlarınızın doğru çalışıp çalışmadığını test etmek amacıyla gönderilmiştir.</p>',
            ],
            'welcome' => [
                'subject' => 'Hoş Geldiniz - {site_name}',
                'body'    => '<p class="em-greeting">Merhaba</p>
<h1 class="em-heading">Hoş Geldiniz, {user_name}! &#127793;</h1>

<p class="em-text">
    {site_name} ailesine katıldığınız için teşekkür ederiz.
    Aramıza hoş geldiniz! Size yardımcı olmaktan mutluluk duyarız.
</p>

<hr class="em-divider">

<p class="em-heading-sm">Hesabınızla neler yapabilirsiniz?</p>

<table role="presentation" cellpadding="0" cellspacing="0" width="100%">
    <tr><td class="em-feature-td"><table role="presentation" cellpadding="0" cellspacing="0"><tr><td class="em-feature-icon-td">&#128100;</td><td class="em-feature-text-td"><strong>Profil bilgilerinizi</strong> yönetin</td></tr></table></td></tr>
    <tr><td class="em-feature-td"><table role="presentation" cellpadding="0" cellspacing="0"><tr><td class="em-feature-icon-td">&#128196;</td><td class="em-feature-text-td"><strong>İçeriklerimizi</strong> keşfedin</td></tr></table></td></tr>
    <tr><td class="em-feature-td"><table role="presentation" cellpadding="0" cellspacing="0"><tr><td class="em-feature-icon-td">&#128227;</td><td class="em-feature-text-td"><strong>Yeni yazılardan</strong> haberdar olun</td></tr></table></td></tr>
    <tr><td class="em-feature-td"><table role="presentation" cellpadding="0" cellspacing="0"><tr><td class="em-feature-icon-td">&#9993;</td><td class="em-feature-text-td"><strong>Bizimle iletişimde</strong> kalın</td></tr></table></td></tr>
</table>

<hr class="em-divider">

<p class="em-text">
    Herhangi bir sorunuz varsa bize iletişim sayfamızdan ulaşabilirsiniz.
    İyi çalışmalar dileriz!
</p>',
            ],
            'verify_email' => [
                'subject' => 'E-posta Adresinizi Doğrulayın - {site_name}',
                'body'    => '<p class="em-greeting">Merhaba</p>
<h1 class="em-heading">E-posta Adresinizi Doğrulayın</h1>

<p class="em-text">
    {user_name}, hesabınızı kullanmaya başlamak için aşağıdaki butona tıklayarak
    e-posta adresinizi doğrulayın.
</p>

<div class="em-btn-wrap">
    <a href="{verification_url}" class="em-btn">E-postamı Doğrula</a>
</div>

<hr class="em-divider">

<p class="em-text-sm">
    Bağlantının geçerlilik süresi 60 dakikadır. Bu hesabı siz oluşturmadıysanız
    bu e-postayı yok sayabilirsiniz.
</p>',
            ],
            'reset_password' => [
                'subject' => 'Şifre Sıfırlama - {site_name}',
                'body'    => '<p class="em-greeting">Güvenlik</p>
<h1 class="em-heading">Şifre Sıfırlama Talebi &#128274;</h1>

<p class="em-text">
    Merhaba, hesabınız için bir şifre sıfırlama talebi aldık.
    Şifrenizi sıfırlamak için aşağıdaki butona tıklayın:
</p>

<div class="em-btn-wrap">
    <a href="{reset_url}" class="em-btn">&#128275; Şifremi Sıfırla</a>
</div>

<table class="em-highlight" role="presentation" cellpadding="0" cellspacing="0" width="100%">
    <tr><td class="em-highlight-td"><p class="em-text-sm">&#9200; Bu şifre sıfırlama bağlantısı <strong>60 dakika</strong> içinde geçerliliğini yitirecektir.</p></td></tr>
</table>

<hr class="em-divider">

<p class="em-text">
    Eğer şifre sıfırlama talebinde bulunmadıysanız, bu e-postayı görmezden gelebilirsiniz.
    Hesabınız güvende.
</p>

<p class="em-text-sm">
    Butona tıklayamıyorsanız aşağıdaki bağlantıyı tarayıcınıza kopyalayıp yapıştırın:<br>
    <a href="{reset_url}">{reset_url}</a>
</p>',
            ],
            'contact_message' => [
                'subject' => 'Yeni İletişim Mesajı - {contact_subject}',
                'body'    => '<p class="em-greeting">İletişim</p>
<h1 class="em-heading">Yeni İletişim Mesajı &#128233;</h1>

<p class="em-text">Web sitesi üzerinden yeni bir iletişim mesajı alındı.</p>

<table class="em-info-box" role="presentation" cellpadding="0" cellspacing="0" width="100%">
    <tr><td class="em-info-box-td">
        <p class="em-info-row"><span class="em-info-label">Gönderen:</span> {contact_name}</p>
        <p class="em-info-row"><span class="em-info-label">E-posta:</span> {contact_email}</p>
        <p class="em-info-row"><span class="em-info-label">Telefon:</span> {contact_phone}</p>
        <p class="em-info-row"><span class="em-info-label">Konu:</span> {contact_subject}</p>
        <p class="em-info-row"><span class="em-info-label">Tarih:</span> {contact_date}</p>
    </td></tr>
</table>

<hr class="em-divider">
<p class="em-heading-sm">Mesaj İçeriği</p>
<p class="em-text">{contact_message}</p>
<hr class="em-divider">

<p class="em-text">Bu mesajı yönetim panelinden görüntüleyebilir ve yanıtlayabilirsiniz.</p>

<div class="em-btn-wrap">
    <a href="{message_url}" class="em-btn">Mesajı Görüntüle</a>
</div>',
            ],
            'contact_reply' => [
                'subject' => 'Re: {contact_subject}',
                'body'    => '<p class="em-greeting">Merhaba {contact_name},</p>
<h1 class="em-heading">Mesajınıza Yanıt &#9993;</h1>

<p class="em-text">İletişim formundan gönderdiğiniz mesajınız için teşekkür ederiz. Yanıtımız aşağıdadır:</p>

<table class="em-highlight" role="presentation" cellpadding="0" cellspacing="0" width="100%">
    <tr><td class="em-highlight-td"><p class="em-text">{reply_body}</p></td></tr>
</table>

<hr class="em-divider">
<p class="em-heading-sm">Orijinal Mesajınız</p>
<p class="em-text" style="font-style: italic; opacity: 0.8;">{contact_message}</p>
<hr class="em-divider">

<p class="em-text">Başka sorularınız varsa bu e-postayı yanıtlayabilir veya web sitemiz üzerinden bize ulaşabilirsiniz.</p>',
            ],
        ];
    }
}
