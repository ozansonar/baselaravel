<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Who is allowed to speak for the visitor.
 *
 * Behind a reverse proxy or a CDN the connection comes from the proxy, so
 * unless that proxy is trusted every request looks like it arrived from one
 * single address. Nothing errors when it is misconfigured — the site simply
 * treats the whole internet as one visitor, which quietly collapses the rate
 * limiters onto a shared bucket, fills the analytics with the proxy's address
 * and keeps HSTS from ever being sent.
 *
 * config/trustedproxy.php holds the list; the header set is pinned in
 * bootstrap/app.php.
 */
class TrustedProxyTest extends TestCase
{
    use RefreshDatabase;

    private const PROXY = '10.1.1.1';

    private const VISITOR = '203.0.113.9';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);

        // The web group is what carries SecurityHeaders, and the HSTS
        // assertion below needs it.
        Route::middleware('web')->get('/__proxy-probe', fn (Request $request) => response()->json([
            'ip'     => $request->ip(),
            'secure' => $request->secure(),
        ]));
    }

    /** @param array<string, string> $headers */
    private function probe(array $headers = []): \Illuminate\Testing\TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => self::PROXY])
            ->withHeaders($headers)
            ->get('/__proxy-probe');
    }

    /**
     * Nothing is trusted until TRUSTED_PROXIES says so. A base kit that
     * shipped with a default here would hand every clone a header a visitor
     * can write themselves.
     */
    public function test_no_proxy_is_trusted_out_of_the_box(): void
    {
        $this->assertNull(config('trustedproxy.proxies'));
    }

    /**
     * The header set is narrower than Laravel's default on purpose: this
     * project runs behind nothing that speaks X-Forwarded-Prefix or the AWS
     * ELB header, and a trusted surface should be no wider than it has to be.
     */
    public function test_only_the_forwarding_headers_we_mean_are_trusted(): void
    {
        $pinned = new \ReflectionProperty(
            \Illuminate\Http\Middleware\TrustProxies::class,
            'alwaysTrustHeaders',
        );

        $this->assertSame(
            Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
            $pinned->getValue(),
        );
    }

    /** @return mixed the parsed proxy list for a given TRUSTED_PROXIES value */
    private function parseEnv(string $value): mixed
    {
        $previous = $_SERVER['TRUSTED_PROXIES'] ?? null;
        $_SERVER['TRUSTED_PROXIES'] = $value;

        try {
            return (require config_path('trustedproxy.php'))['proxies'];
        } finally {
            if ($previous === null) {
                unset($_SERVER['TRUSTED_PROXIES']);
            } else {
                $_SERVER['TRUSTED_PROXIES'] = $previous;
            }
        }
    }

    public function test_the_env_value_is_read_as_a_list(): void
    {
        $this->assertSame(
            ['10.0.0.0/8', '172.16.0.0/12'],
            $this->parseEnv('10.0.0.0/8, 172.16.0.0/12'),
        );
    }

    public function test_the_wildcard_survives_parsing_as_a_wildcard(): void
    {
        $this->assertSame('*', $this->parseEnv('*'));
    }

    public function test_an_empty_env_value_trusts_nothing(): void
    {
        $this->assertNull($this->parseEnv('  '));
    }

    public function test_a_forwarded_address_is_ignored_when_no_proxy_is_trusted(): void
    {
        config(['trustedproxy.proxies' => null]);

        $this->probe(['X-Forwarded-For' => self::VISITOR])
            ->assertJsonPath('ip', self::PROXY);
    }

    public function test_a_forwarded_address_is_used_when_the_proxy_is_trusted(): void
    {
        config(['trustedproxy.proxies' => [self::PROXY]]);

        $this->probe(['X-Forwarded-For' => self::VISITOR])
            ->assertJsonPath('ip', self::VISITOR);
    }

    public function test_an_untrusted_proxy_cannot_forge_the_address(): void
    {
        config(['trustedproxy.proxies' => ['10.9.9.9']]);

        $this->probe(['X-Forwarded-For' => self::VISITOR])
            ->assertJsonPath('ip', self::PROXY);
    }

    public function test_the_wildcard_trusts_every_upstream_hop(): void
    {
        config(['trustedproxy.proxies' => '*']);

        $this->probe(['X-Forwarded-For' => self::VISITOR])
            ->assertJsonPath('ip', self::VISITOR);
    }

    /**
     * A site terminating TLS at the proxy speaks plain HTTP to PHP. Without
     * the forwarded scheme $request->secure() stays false and SecurityHeaders
     * never emits HSTS — the header is the whole reason the scheme is trusted.
     */
    public function test_hsts_is_emitted_when_the_trusted_proxy_reports_https(): void
    {
        config(['trustedproxy.proxies' => [self::PROXY]]);

        $this->probe(['X-Forwarded-Proto' => 'https'])
            ->assertJsonPath('secure', true)
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_the_forwarded_scheme_is_ignored_from_an_untrusted_source(): void
    {
        config(['trustedproxy.proxies' => null]);

        $this->probe(['X-Forwarded-Proto' => 'https'])
            ->assertJsonPath('secure', false)
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    /**
     * The payoff: the login limiter keys on the address, so two visitors
     * behind the same proxy have to land in different buckets. Untrusted, they
     * share one and the sixth attempt of the pair is refused no matter who
     * made it.
     */
    public function test_visitors_behind_a_trusted_proxy_get_their_own_rate_limit_bucket(): void
    {
        config(['trustedproxy.proxies' => [self::PROXY]]);

        $this->assertSame(302, $this->loginAttempts('198.51.100.4', 5));

        // A different visitor, same proxy, same moment.
        $this->assertSame(302, $this->loginAttempts('198.51.100.5', 1));
    }

    /** @return int the status of the final attempt */
    private function loginAttempts(string $visitorIp, int $times): int
    {
        $status = 0;

        for ($i = 0; $i < $times; $i++) {
            $status = $this->withServerVariables(['REMOTE_ADDR' => self::PROXY])
                ->withHeaders(['X-Forwarded-For' => $visitorIp])
                ->post('/tr/giris', [
                    'email'    => 'yok@example.com',
                    'password' => 'yanlis-sifre',
                ])
                ->getStatusCode();
        }

        return $status;
    }
}
