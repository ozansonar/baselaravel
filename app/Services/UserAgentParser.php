<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Tarayıcı kimliğini (User-Agent) okunur parçalara ayırır.
 *
 * Bu mantık AnalyticsService'in içinde iki özel metot olarak duruyordu ve
 * oradan çağrılabilen tek yer analitik kaydıydı. "Cihazlarım" ekranı da aynı
 * ayrıştırmaya ihtiyaç duyunca buraya çıktı.
 *
 * Eskiden jenssegers/agent paketi vardı, yoksa buradaki regex'e düşülüyordu.
 * Paket 2020'den beri güncellenmiyordu ve ikisi sekiz gerçek User-Agent
 * üzerinde karşılaştırıldığında aynı sonucu verdi — iki yerde de daha
 * okunur olan buydu ("macOS 10.15.7" / "OS X 10_15_7", "Android 14" /
 * "AndroidOS 14"). Bağımlılık düşürüldü; ayrıştırma artık tamamen bizim.
 *
 * @phpstan-type ParsedAgent array{is_bot: bool, bot_name: ?string, device_type: string, browser: ?string, browser_version: ?string, os: ?string}
 */
final class UserAgentParser
{
    /**
     * @return ParsedAgent
     */
    public function parse(string $userAgent): array
    {
        return $this->parseWithFallback($userAgent);
    }

    /**
     * Ekranda tek satırda gösterilecek hâli: "Chrome 120 · macOS 14".
     *
     * Hiçbir parça tanınmadıysa boş string değil null dönüyor — çağıran taraf
     * "bilinmeyen cihaz" metnini kendi dilinde basabilsin.
     */
    public function label(string $userAgent): ?string
    {
        $parsed = $this->parse($userAgent);

        $browser = $parsed['browser'];

        if ($browser !== null && $parsed['browser_version'] !== null) {
            // Yalnız ana sürüm: "120.0.6099.109" satırı okunmaz hâle getiriyor.
            $browser .= ' ' . explode('.', $parsed['browser_version'])[0];
        }

        $parts = array_values(array_filter([$browser, $this->readableOs($parsed['os'])]));

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /**
     * Paketin verdiği ham platform adı ekrana uygun değil: sürümü alt çizgiyle
     * ("10_15_7") ve macOS'u eski adıyla ("OS X") yazıyor.
     *
     * Düzeltme bilerek yalnız burada, parse() içinde değil: analitik tablosu
     * yıllardır ham hâli saklıyor ve orada değiştirmek aynı tarayıcıyı iki ayrı
     * satır olarak sayardı.
     */
    private function readableOs(?string $os): ?string
    {
        if ($os === null) {
            return null;
        }

        $os = str_replace('_', '.', $os);

        return preg_replace('/^OS X\b/', 'macOS', $os) ?? $os;
    }

    /**
     * Ayrıştırmanın kendisi: tanınan tarayıcılar, işletim sistemleri ve
     * arama motoru robotları için desenler.
     *
     * @return ParsedAgent
     */
    private function parseWithFallback(string $ua): array
    {
        $uaLower = strtolower($ua);

        $botPatterns = [
            'googlebot'    => 'Googlebot',
            'bingbot'      => 'Bingbot',
            'yandexbot'    => 'YandexBot',
            'baiduspider'  => 'Baiduspider',
            'duckduckbot'  => 'DuckDuckBot',
            'applebot'     => 'Applebot',
            'facebookexternalhit' => 'FacebookBot',
            'twitterbot'   => 'Twitterbot',
            'linkedinbot'  => 'LinkedInBot',
            'whatsapp'     => 'WhatsApp',
            'telegrambot'  => 'TelegramBot',
            'ahrefsbot'    => 'AhrefsBot',
            'semrushbot'   => 'SemrushBot',
            'mj12bot'      => 'MJ12bot',
            'dotbot'       => 'DotBot',
            'petalbot'     => 'PetalBot',
            'gptbot'       => 'GPTBot',
            'claudebot'    => 'ClaudeBot',
            'anthropic'    => 'AnthropicBot',
            'ccbot'        => 'CCBot',
            'perplexitybot' => 'PerplexityBot',
        ];

        $isBot = false;
        $botName = null;

        foreach ($botPatterns as $needle => $name) {
            if (str_contains($uaLower, $needle)) {
                $isBot = true;
                $botName = $name;
                break;
            }
        }

        // Generic bot/crawler/spider kelimeleri — yukarıdaki listede yoksa yakala.
        if (! $isBot && preg_match('/(bot|crawler|spider|crawl)/i', $ua)) {
            $isBot = true;
            $botName = 'Unknown Bot';
        }

        if ($isBot) {
            $deviceType = 'bot';
        } elseif (str_contains($uaLower, 'ipad') || (str_contains($uaLower, 'tablet') && ! str_contains($uaLower, 'mobile'))) {
            $deviceType = 'tablet';
        } elseif (preg_match('/(mobile|iphone|ipod|android.*mobile|blackberry|windows phone)/i', $ua)) {
            $deviceType = 'mobile';
        } elseif ($ua !== '') {
            $deviceType = 'desktop';
        } else {
            $deviceType = 'other';
        }

        $browser = null;
        $browserVersion = null;

        $browserRules = [
            'Edge'    => '/Edg(?:e|A|iOS)?\/([0-9.]+)/i',
            'Opera'   => '/(?:Opera|OPR)\/([0-9.]+)/i',
            'Firefox' => '/Firefox\/([0-9.]+)/i',
            'Chrome'  => '/Chrome\/([0-9.]+)/i',
            'Safari'  => '/Version\/([0-9.]+).*Safari/i',
            'IE'      => '/MSIE ([0-9.]+)|Trident.*rv:([0-9.]+)/i',
        ];

        foreach ($browserRules as $name => $pattern) {
            if (preg_match($pattern, $ua, $m)) {
                $browser = $name;
                $browserVersion = $m[1] ?? ($m[2] ?? null);
                break;
            }
        }

        $os = null;

        if (preg_match('/Windows NT ([0-9.]+)/i', $ua, $m)) {
            $winMap = ['10.0' => '10', '6.3' => '8.1', '6.2' => '8', '6.1' => '7', '6.0' => 'Vista', '5.1' => 'XP'];
            $os = 'Windows ' . ($winMap[$m[1]] ?? $m[1]);
        } elseif (preg_match('/Mac OS X ([0-9_\.]+)/i', $ua, $m)) {
            $os = 'macOS ' . str_replace('_', '.', $m[1]);
        } elseif (preg_match('/Android ([0-9.]+)/i', $ua, $m)) {
            $os = 'Android ' . $m[1];
        } elseif (preg_match('/(?:iPhone OS|CPU OS) ([0-9_]+)/i', $ua, $m)) {
            $os = 'iOS ' . str_replace('_', '.', $m[1]);
        } elseif (str_contains($uaLower, 'linux')) {
            $os = 'Linux';
        }

        return [
            'is_bot'          => $isBot,
            'bot_name'        => $botName,
            'device_type'     => $deviceType,
            'browser'         => $browser,
            'browser_version' => $browserVersion,
            'os'              => $os,
        ];
    }
}
