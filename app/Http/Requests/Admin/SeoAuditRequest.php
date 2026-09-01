<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\PermissionKey;
use App\Support\Seo\SeoSubject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form ekranından gelen denetim isteği.
 *
 * Gövde henüz kaydedilmemiş içeriğin kendisi — yazar hâlâ yazıyor. Bu yüzden
 * doğrulama gevşek: eksik alan bir hata değil, denetimin bulacağı şeyin ta
 * kendisi. Sıkı doğrulama yapmak, denetimin en çok işe yarayacağı anda (form
 * yarım) çalışmamasına yol açardı.
 *
 * Sınırlanan tek şey **boyut**: uç, kimliği doğrulanmış bir yöneticiye açık ama
 * sınırsız gövde kabul etmesi için bir sebep yok.
 */
final class SeoAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Denetim içeriği okuyor ve içerik hakkında konuşuyor: sayfa ya da yazı
        // düzenleyebilen herkes çağırabilmeli, başkası çağıramamalı.
        $user = $this->user();

        return $user !== null
            && ($user->hasPermission(PermissionKey::PagesManage)
                || $user->hasPermission(PermissionKey::BlogPostsManage));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'locale'           => ['nullable', 'string', 'max:10'],
            'type'             => ['nullable', Rule::in(['page', 'blog_post'])],
            'title'            => ['nullable', 'string', 'max:500'],
            'slug'             => ['nullable', 'string', 'max:500'],
            'meta_title'       => ['nullable', 'string', 'max:500'],
            'meta_description' => ['nullable', 'string', 'max:2000'],
            'image'            => ['nullable', 'string', 'max:500'],
            // Zengin metin gövdesi: tavan cömert ama sınırsız değil.
            'body'             => ['nullable', 'string', 'max:500000'],
            'content'          => ['nullable', 'string', 'max:500000'],
        ];
    }

    /**
     * Denetimin göreceği hâl.
     */
    public function subject(): SeoSubject
    {
        return SeoSubject::fromArray($this->validated());
    }
}
