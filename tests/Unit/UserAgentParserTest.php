<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\UserAgentParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * User-Agent ayrıştırıcısı.
 *
 * jenssegers/agent paketi kaldırıldığında bu sınavlar onun yerini aldı:
 * ayrıştırma artık tamamen bizim ve doğruluğunu kanıtlayan tek şey burası.
 * Örnekler uydurma değil — gerçek tarayıcıların gönderdiği satırlar.
 */
class UserAgentParserTest extends TestCase
{
    private function parser(): UserAgentParser
    {
        return new UserAgentParser();
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: ?string, 3: string}>
     */
    public static function agents(): array
    {
        return [
            'Windows Chrome' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Chrome', 'Windows 10', 'desktop',
            ],
            'macOS Safari' => [
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15',
                'Safari', 'macOS 10.15.7', 'desktop',
            ],
            'iPhone Safari' => [
                'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1',
                'Safari', 'iOS 17.2', 'mobile',
            ],
            'Android Chrome' => [
                'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
                'Chrome', 'Android 14', 'mobile',
            ],
            'iPad' => [
                'Mozilla/5.0 (iPad; CPU OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1',
                'Safari', 'iOS 17.2', 'tablet',
            ],
            'Edge' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0',
                'Edge', 'Windows 10', 'desktop',
            ],
            'Firefox on Linux' => [
                'Mozilla/5.0 (X11; Linux x86_64; rv:121.0) Gecko/20100101 Firefox/121.0',
                'Firefox', 'Linux', 'desktop',
            ],
        ];
    }

    #[DataProvider('agents')]
    public function test_it_reads_real_world_user_agents(string $ua, string $browser, ?string $os, string $device): void
    {
        $parsed = $this->parser()->parse($ua);

        $this->assertSame($browser, $parsed['browser']);
        $this->assertSame($os, $parsed['os']);
        $this->assertSame($device, $parsed['device_type']);
        $this->assertFalse($parsed['is_bot']);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function bots(): array
    {
        return [
            'Googlebot' => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', 'Googlebot'],
            'Bingbot'   => ['Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)', 'Bingbot'],
            'GPTBot'    => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; GPTBot/1.0; +https://openai.com/gptbot', 'GPTBot'],
            'Bilinmeyen' => ['SomeRandomCrawler/1.0', 'Unknown Bot'],
        ];
    }

    #[DataProvider('bots')]
    public function test_it_recognises_robots(string $ua, string $name): void
    {
        $parsed = $this->parser()->parse($ua);

        $this->assertTrue($parsed['is_bot']);
        $this->assertSame($name, $parsed['bot_name']);
        $this->assertSame('bot', $parsed['device_type']);
    }

    /**
     * Boş kimlik satırı hiçbir şey uydurmamalı: "bilinmiyor" demek,
     * bilinmeyen bir tarayıcıyı Chrome saymaktan iyidir.
     */
    public function test_an_empty_user_agent_produces_nothing(): void
    {
        $parsed = $this->parser()->parse('');

        $this->assertNull($parsed['browser']);
        $this->assertNull($parsed['os']);
        $this->assertSame('other', $parsed['device_type']);
        $this->assertFalse($parsed['is_bot']);
    }

    public function test_the_label_is_short_and_readable(): void
    {
        $label = $this->parser()->label(
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.6099.109 Safari/537.36',
        );

        // Yalnız ana sürüm: "120.0.6099.109" satırı okunmaz hâle getiriyor.
        $this->assertSame('Chrome 120 · macOS 10.15.7', $label);
    }

    public function test_the_label_is_null_when_nothing_is_recognised(): void
    {
        $this->assertNull($this->parser()->label(''));
    }
}
