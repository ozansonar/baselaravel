<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Tek bir sunucu hatası — kaç kez olduğu, nereden geldiği ve çözülüp
 * çözülmediğiyle birlikte.
 *
 * Satır hata **başına** tek: aynı kusurun bininci tekrarı yeni bir kayıt değil,
 * sayaçta bir artış. Liste böylece "kaç farklı sorunum var" sorusunu
 * cevaplıyor; "kaç kez patladı" sorusunun cevabı da satırın içinde duruyor.
 */
final class ErrorLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'fingerprint',
        'exception',
        'message',
        'file',
        'line',
        'trace',
        'url',
        'method',
        'ip_address',
        'user_agent',
        'user_id',
        'occurrences',
        'first_seen_at',
        'last_seen_at',
        'resolved_at',
        'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'line'          => 'integer',
            'occurrences'   => 'integer',
            'user_id'       => 'integer',
            'resolved_by'   => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at'  => 'datetime',
            'resolved_at'   => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    /**
     * Hata sınıfının kısa adı — `Illuminate\Database\QueryException` yerine
     * `QueryException`. Tam adı detay ekranında duruyor.
     */
    public function shortException(): string
    {
        return class_basename($this->exception);
    }

    /**
     * Proje köküne göre konum.
     *
     * Mutlak yol satıra sığmıyor ve paylaşımlı hosting'de kullanıcı adını
     * ekrana taşıyor: `/home/musteri123/public_html/app/...`.
     */
    public function location(): string
    {
        return $this->relativeFile() . ':' . $this->line;
    }

    public function relativeFile(): string
    {
        $root = base_path() . DIRECTORY_SEPARATOR;

        return str_starts_with($this->file, $root)
            ? substr($this->file, strlen($root))
            : $this->file;
    }

    /**
     * Listeye sığan konum: yolun yalnız son iki parçası.
     *
     * Kuyruktan kısaltmak işe yaramıyor — dosya adı ve satır numarası orada
     * duruyor ve bakılan tek şey onlar. `…/Translation/Translator.php:176`,
     * `vendor/laravel/framework/src/Illuminate/Transl…` hâlinden hem kısa hem
     * daha çok bilgi taşıyor. Tam yol başlık niteliğinde (`title`) veriliyor.
     */
    public function shortLocation(int $segments = 2): string
    {
        $parts = explode(DIRECTORY_SEPARATOR, $this->relativeFile());

        if (count($parts) <= $segments) {
            return $this->location();
        }

        return '…/' . implode(DIRECTORY_SEPARATOR, array_slice($parts, -$segments)) . ':' . $this->line;
    }

    /**
     * Hatanın kaynağı: projenin kendi kodu mu, çerçeve/paket mi?
     *
     * Ayrım işe yarıyor — `vendor/` altında patlayan bir hatanın sebebi
     * neredeyse her zaman onu çağıran kendi kodumuz, ama düzeltilecek yer
     * orası değil.
     */
    public function isVendor(): bool
    {
        return str_starts_with($this->relativeFile(), 'vendor' . DIRECTORY_SEPARATOR);
    }

    /**
     * Listede gösterilen özet. Boş mesajlı hatalar (çoğu `TypeError`) sınıf
     * adıyla anılıyor, boş hücre bırakılmıyor.
     */
    public function summary(int $limit = 120): string
    {
        $message = trim((string) $this->message);

        return Str::limit($message !== '' ? $message : $this->shortException(), $limit);
    }
}
