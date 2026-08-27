<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PersonName;
use PHPUnit\Framework\TestCase;

/**
 * Ad ve soyadın tek parça metinle arasındaki çeviri.
 *
 * Mailing tarafında ikisi ayrı sütunlarda tutuluyor ama dışarıdan gelen veri
 * hep ayrı gelmiyor: eski kayıtlar ve tek sütunlu Excel dosyaları birleşik
 * metin veriyor. Bölme kuralı tek yerde durduğu için burada sınanıyor.
 */
class PersonNameTest extends TestCase
{
    public function test_the_last_word_becomes_the_last_name(): void
    {
        $this->assertSame(
            ['first_name' => 'Ahmet', 'last_name' => 'Yılmaz'],
            PersonName::split('Ahmet Yılmaz'),
        );
    }

    /**
     * İki adı olan biri soyadını kaybetmemeli: yalnızca son kelime soyad.
     */
    public function test_only_the_final_word_is_taken_as_the_last_name(): void
    {
        $this->assertSame(
            ['first_name' => 'Ali Can', 'last_name' => 'Yılmaz'],
            PersonName::split('Ali Can Yılmaz'),
        );
    }

    public function test_a_single_word_stays_the_first_name(): void
    {
        $this->assertSame(
            ['first_name' => 'Zeynep', 'last_name' => null],
            PersonName::split('Zeynep'),
        );
    }

    public function test_extra_whitespace_is_collapsed(): void
    {
        $this->assertSame(
            ['first_name' => 'Ahmet', 'last_name' => 'Yılmaz'],
            PersonName::split("  Ahmet\t  Yılmaz \n"),
        );
    }

    public function test_an_empty_name_yields_nothing(): void
    {
        $this->assertSame(['first_name' => null, 'last_name' => null], PersonName::split(null));
        $this->assertSame(['first_name' => null, 'last_name' => null], PersonName::split('   '));
    }

    public function test_the_parts_are_joined_back_for_display(): void
    {
        $this->assertSame('Ahmet Yılmaz', PersonName::full('Ahmet', 'Yılmaz'));
        $this->assertSame('Ahmet', PersonName::full('Ahmet', null));
        $this->assertSame('Yılmaz', PersonName::full(null, 'Yılmaz'));
        $this->assertNull(PersonName::full(null, null));
        $this->assertNull(PersonName::full('  ', ''));
    }
}
