<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\BlogPost;
use App\Models\Page;
use Illuminate\Database\Eloquent\Model;

/**
 * Ek taşıyabilen içerik türleri.
 *
 * Yükleme isteği hangi içeriğe iliştirileceğini söylemek zorunda. Sınıf adı
 * doğrudan istekten okunsaydı, istemci `App\Models\User` yazıp ekin sahibini
 * uydurabilirdi; istek buradaki kısa anahtarı gönderiyor ve sınıf adı yalnızca
 * bu listeden geliyor.
 *
 * Yeni bir içerik türü ek taşıyacaksa tek yapılacak buraya bir vaka eklemek:
 * model HasContentFiles kullanır, ekran ortak partial'ı çağırır, gerisi
 * kendiliğinden çalışır.
 */
enum AttachableContent: string
{
    case BlogPost = 'blog-post';
    case Page = 'page';

    /**
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::BlogPost => BlogPost::class,
            self::Page     => Page::class,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::BlogPost => 'İçerik',
            self::Page     => 'Sayfa',
        };
    }

    /**
     * Modelden anahtara dönüş — kayıtlı bir ekin hangi ekrana ait olduğunu
     * bulmak için.
     */
    public static function fromModel(Model $model): ?self
    {
        foreach (self::cases() as $case) {
            if ($model instanceof ($case->modelClass())) {
                return $case;
            }
        }

        return null;
    }
}
