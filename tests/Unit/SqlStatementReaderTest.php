<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SqlStatementReader;
use PHPUnit\Framework\TestCase;

/**
 * SQL dökümünü ifadelere ayırma.
 *
 * Geri yüklemenin en sessiz kırılma noktası burası: naif bir "noktalı
 * virgülden böl" çözümü, bir metin alanının içindeki noktalı virgülde ifadeyi
 * ortasından keser ve veri yarım döner. Hata da vermez — içeri giren SQL
 * sözdizimsel olarak geçerli görünebilir.
 */
class SqlStatementReaderTest extends TestCase
{
    /** @return list<string> */
    private function read(string $sql): array
    {
        return iterator_to_array((new SqlStatementReader())->fromString($sql), false);
    }

    public function test_it_splits_plain_statements(): void
    {
        $this->assertSame(
            ['SELECT 1', 'SELECT 2'],
            $this->read('SELECT 1; SELECT 2;'),
        );
    }

    public function test_a_missing_final_semicolon_does_not_lose_the_statement(): void
    {
        $this->assertSame(['SELECT 1'], $this->read('SELECT 1'));
    }

    public function test_empty_statements_are_dropped(): void
    {
        $this->assertSame(['SELECT 1'], $this->read(";;\n\n SELECT 1; ;"));
    }

    /**
     * Asıl mesele: kullanıcı verisinin içindeki noktalı virgül.
     */
    public function test_a_semicolon_inside_a_string_does_not_split(): void
    {
        $this->assertSame(
            ["INSERT INTO t VALUES ('Merhaba; dünya')"],
            $this->read("INSERT INTO t VALUES ('Merhaba; dünya');"),
        );
    }

    public function test_an_escaped_quote_does_not_end_the_string(): void
    {
        $this->assertSame(
            ["INSERT INTO t VALUES ('O\\'nun; kitabı')"],
            $this->read("INSERT INTO t VALUES ('O\\'nun; kitabı');"),
        );
    }

    public function test_a_doubled_quote_does_not_end_the_string(): void
    {
        $this->assertSame(
            ["INSERT INTO t VALUES ('iki '' tırnak; bir ifade')"],
            $this->read("INSERT INTO t VALUES ('iki '' tırnak; bir ifade');"),
        );
    }

    public function test_double_quoted_strings_are_respected(): void
    {
        $this->assertSame(
            ['INSERT INTO t VALUES ("noktalı; virgül")'],
            $this->read('INSERT INTO t VALUES ("noktalı; virgül");'),
        );
    }

    /**
     * Geri tırnak içinde ters bölü kaçış karakteri değildir; öyle sayılsaydı
     * `` `a\` `` kolonu tırnağı hiç kapatmaz ve kalan döküm yutulurdu.
     */
    public function test_a_backslash_inside_backticks_is_literal(): void
    {
        $this->assertSame(
            ['SELECT `a\\` FROM t', 'SELECT 2'],
            $this->read('SELECT `a\\` FROM t; SELECT 2;'),
        );
    }

    public function test_line_comments_are_dropped(): void
    {
        $this->assertSame(
            ['SELECT 1', 'SELECT 2'],
            $this->read("-- başlık\nSELECT 1;\n# başka yorum\nSELECT 2;"),
        );
    }

    public function test_a_comment_marker_inside_a_string_is_data(): void
    {
        $this->assertSame(
            ["INSERT INTO t VALUES ('-- yorum değil')"],
            $this->read("INSERT INTO t VALUES ('-- yorum değil');"),
        );
    }

    public function test_block_comments_are_dropped(): void
    {
        $this->assertSame(
            ['SELECT 1'],
            $this->read("/* çok\n satırlı yorum; içinde noktalı virgül */ SELECT 1;"),
        );
    }

    /**
     * `/*! ... *\/` çalıştırılabilir yorumdur: mysqldump karakter kümesini ve
     * kısıt ayarlarını bu biçimde yazar. Atılırsa geri yükleme yabancı anahtar
     * hatalarına takılır.
     */
    public function test_executable_comments_are_kept(): void
    {
        $this->assertSame(
            ['/*!40101 SET NAMES utf8mb4 */', 'SELECT 1'],
            $this->read("/*!40101 SET NAMES utf8mb4 */;\nSELECT 1;"),
        );
    }

    /**
     * Dosya 64 KB'lık parçalar hâlinde okunuyor. İleri-bakış gerektiren
     * kalıplar (`/*!`, `--`, ikilenmiş tırnak) tam parça sınırına denk
     * geldiğinde kaçmamalı — bu testler o sınırı bilerek kalıbın ortasına
     * getiriyor.
     */
    public function test_a_pattern_split_across_a_chunk_boundary_survives(): void
    {
        $reader = new SqlStatementReader();

        foreach (['/*!40101 SET NAMES utf8 */', "-- yorum\nSELECT 9", "SELECT 'a''b'"] as $tail) {
            for ($padding = 65530; $padding <= 65538; $padding++) {
                $sql = 'SELECT ' . str_repeat('0', $padding) . '; ' . $tail . ';';

                $path = tempnam(sys_get_temp_dir(), 'sqlreader_');
                file_put_contents($path, $sql);

                $statements = iterator_to_array($reader->fromFile($path), false);

                @unlink($path);

                $this->assertCount(2, $statements, "dolgu {$padding}, kuyruk {$tail}");
                $this->assertSame(
                    trim(str_replace(["-- yorum\n"], '', $tail)),
                    $statements[1],
                    "dolgu {$padding}",
                );
            }
        }
    }

    public function test_a_real_dump_shape_is_parsed(): void
    {
        $sql = <<<'SQL'
        -- PHP-side DB dump
        SET FOREIGN_KEY_CHECKS=0;

        DROP TABLE IF EXISTS `pages`;
        CREATE TABLE `pages` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `title` varchar(191) NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB;

        INSERT INTO `pages` VALUES (1,'Hakkımızda; kurumsal');
        INSERT INTO `pages` VALUES (2,'O\'nun sayfası');

        SET FOREIGN_KEY_CHECKS=1;
        SQL;

        $statements = $this->read($sql);

        $this->assertCount(6, $statements);
        $this->assertSame('SET FOREIGN_KEY_CHECKS=0', $statements[0]);
        $this->assertStringStartsWith('DROP TABLE IF EXISTS', $statements[1]);
        $this->assertStringContainsString('Hakkımızda; kurumsal', $statements[3]);
        $this->assertSame('SET FOREIGN_KEY_CHECKS=1', $statements[5]);
    }

    public function test_a_missing_file_yields_nothing(): void
    {
        $statements = iterator_to_array(
            (new SqlStatementReader())->fromFile('/olmayan/dosya.sql'),
            false,
        );

        $this->assertSame([], $statements);
    }
}
