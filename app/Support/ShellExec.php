<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Shared hosting'de shell_exec / exec gibi fonksiyonlar 3 farklı yolla
 * kapatılmış olabilir:
 *
 *   1. PHP compile-time disable (`./configure --disable-shell-exec`)
 *      → function_exists() false döner, çağrı "undefined function" fatal
 *
 *   2. ini disable_functions listesinde
 *      → function_exists() bazı PHP sürümlerinde TRUE dönebilir (!),
 *        çağrı runtime'da fatal verir. `@` operatörü bu fatal'i bastırmaz.
 *
 *   3. Suhosin / mod_security gibi ek katmanlar
 *      → çağrı yapılır ama exception veya boş döner
 *
 * Bu helper üç durumu da yakalar:
 *   - function_exists check
 *   - disable_functions ini parse
 *   - try/catch wrapper
 *
 * Kullanım:
 *   $output = ShellExec::run('which mysqldump');   // disabled ise null
 *   if (ShellExec::isAvailable()) { ... }
 */
final class ShellExec
{
    /** @var array<string>|null disabled function listesi cache */
    private static ?array $disabledFunctions = null;

    /**
     * shell_exec gerçekten çağrılabilir mi (üç katmanlı kontrol)?
     */
    public static function isAvailable(): bool
    {
        return self::isFunctionAvailable('shell_exec');
    }

    /**
     * exec() (output array'le) çağrılabilir mi?
     */
    public static function isExecAvailable(): bool
    {
        return self::isFunctionAvailable('exec');
    }

    /**
     * Shell komut çalıştır — disabled ise null döner, ASLA fatal vermez.
     * @ operatörü "undefined function" fatal'ini bastırmaz; bu yüzden
     * pre-check + try/catch ikisi de gerekli.
     */
    public static function run(string $command): ?string
    {
        if (! self::isAvailable()) {
            return null;
        }

        try {
            $output = @shell_exec($command);
            return is_string($output) ? $output : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * exec() fallback — shell_exec yoksa ama exec varsa.
     *
     * @return ?string Çıktının newline-join hali, fail ise null
     */
    public static function runExec(string $command): ?string
    {
        if (! self::isExecAvailable()) {
            return null;
        }

        try {
            $lines = [];
            $exitCode = 0;
            @exec($command, $lines, $exitCode);
            if ($exitCode === 0 && ! empty($lines)) {
                return implode("\n", $lines);
            }
            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * shell_exec varsa onu, yoksa exec'i dene.
     * En agresif fallback — sadece "komut çıktı verdi mi" lazımsa kullan.
     */
    public static function runAny(string $command): ?string
    {
        return self::run($command) ?? self::runExec($command);
    }

    /**
     * Üç katmanlı function availability check.
     *
     *  1. function_exists() false → kesinlikle yok
     *  2. disable_functions listesinde → fatal verir, yok say
     *  3. function_exists true ve disabled değil → çalışmalı
     */
    private static function isFunctionAvailable(string $func): bool
    {
        if (! function_exists($func)) {
            return false;
        }
        if (in_array($func, self::disabledFunctions(), true)) {
            return false;
        }
        return true;
    }

    /**
     * php.ini disable_functions listesini parse et + cache.
     *
     * @return array<string>
     */
    private static function disabledFunctions(): array
    {
        if (self::$disabledFunctions !== null) {
            return self::$disabledFunctions;
        }

        $raw = (string) ini_get('disable_functions');
        if ($raw === '') {
            return self::$disabledFunctions = [];
        }

        $list = array_map('trim', explode(',', $raw));
        $list = array_filter($list, static fn (string $name): bool => $name !== '');

        return self::$disabledFunctions = array_values($list);
    }
}
