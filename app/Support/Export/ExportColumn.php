<?php

declare(strict_types=1);

namespace App\Support\Export;

use Closure;

/**
 * Dışa aktarılan tablonun tek bir sütunu.
 *
 * Sütun kendi başlığını, değeri satırdan nasıl çıkaracağını ve ne kadar yer
 * kapladığını bilir; Excel ve PDF yazıcıları aynı tanımı okur. Böylece bir
 * listenin sütunları iki ayrı yerde tarif edilmek zorunda kalmaz.
 */
final readonly class ExportColumn
{
    /**
     * @param Closure(mixed): mixed $value satırdan ham değeri çıkarır
     * @param float $weight yaklaşık karakter genişliği; hem Excel sütun
     *                      genişliğini hem PDF'in dikey/yatay kararını besler
     */
    private function __construct(
        public string $label,
        public Closure $value,
        public ExportValueType $type,
        public float $weight,
    ) {}

    /**
     * @param Closure(mixed): mixed $value
     */
    public static function make(string $label, Closure $value): self
    {
        return new self($label, $value, ExportValueType::Text, 20.0);
    }

    public function asNumber(): self
    {
        return new self($this->label, $this->value, ExportValueType::Number, $this->weight);
    }

    public function asDate(): self
    {
        return new self($this->label, $this->value, ExportValueType::Date, $this->weight);
    }

    public function asDateTime(): self
    {
        return new self($this->label, $this->value, ExportValueType::DateTime, $this->weight);
    }

    public function width(float $weight): self
    {
        return new self($this->label, $this->value, $this->type, $weight);
    }

    /** Satırdan bu sütunun ham değerini çıkarır. */
    public function resolve(mixed $row): mixed
    {
        return ($this->value)($row);
    }
}
