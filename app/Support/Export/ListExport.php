<?php

declare(strict_types=1);

namespace App\Support\Export;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use LogicException;

/**
 * Bir listeleme ekranının dışa aktarma tanımı.
 *
 * Tanım yalnızca "ne aktarılacak" sorusunu yanıtlar: hangi başlık, hangi
 * sütunlar, hangi sorgu, hangi yetki. "Nasıl aktarılacak" sorusu Excel ve PDF
 * yazıcılarında bir kez çözülür — böylece her liste için iki ayrı aktarma
 * kodu yazılmaz.
 *
 * Sorgu ve süzgeç anahtarları ilgili Service'ten alınır. Liste ekranı da aynı
 * kaynağı kullandığı için ekranda görünen ile dosyaya inen zamanla ayrışmaz.
 */
abstract class ListExport
{
    /** Dosya ve belge adı olarak görünen başlık. */
    abstract public function title(): string;

    /** @return list<ExportColumn> */
    abstract public function columns(): array;

    /**
     * Süzgeçler uygulanmış, sayfalanmamış sorgu.
     *
     * Satırların varsayılan kaynağı budur. Arkasında tablo olmayan listeler
     * (örneğin diskten okunan yedekler) bunun yerine count() ve eachChunk()
     * metodlarını değiştirir.
     *
     * @param array<string, mixed> $filters
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    public function query(array $filters): Builder
    {
        throw new LogicException(static::class . ' sorgu üzerinden gezilmiyor; count() ve eachChunk() değiştirilmeli.');
    }

    /**
     * Liste ekranının tanıdığı süzgeç anahtarları.
     *
     * @return list<string>
     */
    abstract public function filterKeys(): array;

    /** Yetki kontrolü; yetkisiz kullanıcı için istisna fırlatır. */
    abstract public function authorize(): void;

    /**
     * Süzgeçlere uyan kayıt sayısı.
     *
     * @param array<string, mixed> $filters
     */
    public function count(array $filters): int
    {
        return $this->query($filters)->count();
    }

    /**
     * Kayıtları parçalar hâlinde gezer.
     *
     * Varsayılan yol sorgudur: hiçbir noktada sonuç kümesinin tamamı belleğe
     * alınmaz. Sorguya sığmayan listeler (örneğin JSON sütununa göre süzülen
     * mail şablonları) bu metodu kendi kaynaklarıyla değiştirir.
     *
     * @param array<string, mixed> $filters
     * @param callable(\Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model>): void $handler
     */
    public function eachChunk(array $filters, int $size, callable $handler): void
    {
        $this->query($filters)->chunk($size, static function ($records) use ($handler): void {
            $handler($records);
        });
    }

    /**
     * İstekteki süzgeçleri tanıma göre ayıklar.
     *
     * Boş değerler atılır: "?search=" gibi taşıyıcı parametreler süzgeç sayılıp
     * dosyayı ekranda görünenden farklı hâle getirmemeli.
     *
     * @return array<string, mixed>
     */
    public function filtersFromRequest(Request $request): array
    {
        return array_filter(
            $request->only($this->filterKeys()),
            static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== [],
        );
    }
}
