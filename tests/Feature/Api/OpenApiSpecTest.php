<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * `docs/openapi.json` ile gerçek rotaların aynı şeyi söylemesi.
 *
 * Elle yazılan bir şema, yazıldığı gün doğru olup ertesi hafta yalan söylemeye
 * başlar. Mobil ekip modellerini ondan ürettiği için yalanı çalışma zamanında —
 * kullanıcıda — öğrenir.
 *
 * Buradaki sınamalar iki yönde de bekçilik ediyor: şemada olmayan bir uç
 * eklenemez, olmayan bir uç şemada duramaz. Kimlik gerektiren uçların şemada da
 * öyle işaretli olması ayrıca denetleniyor; "bu uç herkese açık" diyen yanlış
 * bir satır, ekibi günlerce yanlış yola sokar.
 */
class OpenApiSpecTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $spec;

    protected function setUp(): void
    {
        parent::setUp();

        $raw = (string) file_get_contents(base_path('docs/openapi.json'));
        $decoded = json_decode($raw, true);

        $this->assertIsArray($decoded, 'docs/openapi.json geçerli JSON değil.');

        $this->spec = $decoded;
    }

    public function test_the_document_declares_what_it_is(): void
    {
        $this->assertSame('3.1.0', $this->spec['openapi'] ?? null);
        $this->assertNotEmpty($this->spec['info']['title'] ?? null);
        $this->assertNotEmpty($this->spec['servers'] ?? null);
    }

    public function test_every_endpoint_is_documented(): void
    {
        $missing = array_diff(array_keys($this->routeOperations()), array_keys($this->specOperations()));

        sort($missing);

        $this->assertSame(
            [],
            $missing,
            "Şemada eksik uç — docs/openapi.json güncellenmeli:\n  " . implode("\n  ", $missing),
        );
    }

    public function test_the_document_invents_nothing(): void
    {
        $extra = array_diff(array_keys($this->specOperations()), array_keys($this->routeOperations()));

        sort($extra);

        $this->assertSame(
            [],
            $extra,
            "Şemada var, uygulamada yok — kaldırılmalı:\n  " . implode("\n  ", $extra),
        );
    }

    /**
     * "Bu uç herkese açık" diyen yanlış bir satır, ekibi günlerce yanlış yola
     * sokar; tersi de öyle.
     */
    public function test_the_document_agrees_about_who_may_call_what(): void
    {
        $wrong = [];
        $spec = $this->specOperations();

        foreach ($this->routeOperations() as $key => $route) {
            if (! isset($spec[$key])) {
                continue;
            }

            $needsToken = in_array('auth:sanctum', $route->gatherMiddleware(), true);
            $documented = ($spec[$key]['security'] ?? []) !== [];

            if ($needsToken !== $documented) {
                $wrong[] = $key . ($needsToken ? ' — jeton istiyor ama şema açık diyor' : ' — açık ama şema jeton istiyor diyor');
            }
        }

        sort($wrong);

        $this->assertSame([], $wrong, "Kimlik bilgisi uyuşmuyor:\n  " . implode("\n  ", $wrong));
    }

    /**
     * Önbelleklenen uçlar 304 dönebiliyor. İstemci bunu bilmezse boş gövdeyi
     * ayrıştırmaya kalkar.
     */
    public function test_cacheable_endpoints_document_their_304(): void
    {
        $wrong = [];
        $spec = $this->specOperations();

        foreach ($this->routeOperations() as $key => $route) {
            if (! isset($spec[$key])) {
                continue;
            }

            $cached = collect($route->gatherMiddleware())
                ->contains(fn ($m): bool => is_string($m) && str_starts_with($m, 'cache.headers'));

            $documented = isset($spec[$key]['responses']['304']);

            if ($cached !== $documented) {
                $wrong[] = $key . ($cached ? ' — 304 dönebiliyor ama şemada yok' : ' — şemada 304 var ama uç önbelleklenmiyor');
            }
        }

        sort($wrong);

        $this->assertSame([], $wrong, "304 bildirimi uyuşmuyor:\n  " . implode("\n  ", $wrong));
    }

    /**
     * Kırık bir $ref, şemayı okuyan her aracı (kod üreticiler dahil) durdurur.
     */
    public function test_every_reference_resolves(): void
    {
        $broken = [];

        foreach ($this->collectRefs($this->spec) as $ref) {
            $path = explode('/', ltrim($ref, '#/'));
            $node = $this->spec;

            foreach ($path as $segment) {
                if (! is_array($node) || ! array_key_exists($segment, $node)) {
                    $broken[] = $ref;
                    continue 2;
                }

                $node = $node[$segment];
            }
        }

        $this->assertSame([], array_values(array_unique($broken)), 'Çözülemeyen $ref: ' . implode(', ', $broken));
    }

    /**
     * Her işlemin benzersiz bir operationId'si olmalı: kod üreticiler istemci
     * metotlarını o addan türetiyor, çakışma sessizce metot ezdiriyor.
     */
    public function test_operation_ids_are_unique(): void
    {
        $ids = [];

        foreach ($this->specOperations() as $operation) {
            $ids[] = $operation['operationId'] ?? null;
        }

        $this->assertNotContains(null, $ids, 'operationId olmayan işlem var.');
        $this->assertSame(
            array_unique($ids),
            $ids,
            'Yinelenen operationId: ' . implode(', ', array_diff_assoc($ids, array_unique($ids))),
        );
    }

    /**
     * Yarım bırakılmış bir işlem şemayı bozmadan içeri girebilir; kod
     * üreticiler ise adsız metotlar ve açıklamasız yanıtlar üretir.
     */
    public function test_every_operation_is_complete(): void
    {
        $incomplete = [];

        foreach ($this->specOperations() as $key => $operation) {
            foreach (['summary', 'tags', 'responses'] as $required) {
                if (empty($operation[$required])) {
                    $incomplete[] = "{$key} — {$required} yok";
                }
            }

            foreach ($operation['responses'] ?? [] as $code => $response) {
                // Ortak yanıtlar components altında tanımlı; açıklamaları orada.
                if (! isset($response['$ref']) && empty($response['description'])) {
                    $incomplete[] = "{$key} {$code} — description yok";
                }
            }
        }

        sort($incomplete);

        $this->assertSame([], $incomplete, "Eksik işlem tanımı:\n  " . implode("\n  ", $incomplete));
    }

    /**
     * Yoldaki her {parametre} tanımlanmış olmalı; tanımsızsa kod üreticiler
     * çağrılabilir olmayan bir istemci metodu üretir.
     */
    public function test_every_path_placeholder_is_declared(): void
    {
        $missing = [];

        foreach ($this->specOperations() as $key => $operation) {
            [, $path] = explode(' ', $key, 2);

            preg_match_all('/\{([^}]+)\}/', $path, $matches);

            $declared = collect($operation['parameters'] ?? [])
                ->reject(fn (array $p): bool => isset($p['$ref']))
                ->pluck('name')
                ->all();

            foreach ($matches[1] as $placeholder) {
                if (! in_array($placeholder, $declared, true)) {
                    $missing[] = "{$key} — {{$placeholder}} tanımlı değil";
                }
            }
        }

        sort($missing);

        $this->assertSame([], $missing, implode("\n  ", $missing));
    }

    // ── Yardımcılar ──

    /**
     * Uygulamadaki uçlar: "GET /blog/posts" => rota.
     *
     * @return array<string, RoutingRoute>
     */
    private function routeOperations(): array
    {
        $prefix = trim((string) config('api.prefix', 'api/v1'), '/');
        $found = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (! str_starts_with($uri, $prefix . '/')) {
                continue;
            }

            // Yakalayıcı rota bir uç değil, bilinmeyen adreslerin cevabı.
            if (str_contains($uri, 'fallbackPlaceholder')) {
                continue;
            }

            $path = '/' . substr($uri, strlen($prefix) + 1);

            foreach ($route->methods() as $method) {
                if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }

                $found[$method . ' ' . $path] = $route;
            }
        }

        return $found;
    }

    /**
     * Şemadaki işlemler, aynı anahtarla.
     *
     * @return array<string, array<string, mixed>>
     */
    private function specOperations(): array
    {
        $found = [];

        foreach (($this->spec['paths'] ?? []) as $path => $operations) {
            foreach ($operations as $method => $operation) {
                $found[strtoupper($method) . ' ' . $path] = $operation;
            }
        }

        return $found;
    }

    /**
     * @param array<mixed> $node
     * @return list<string>
     */
    private function collectRefs(array $node): array
    {
        $refs = [];

        foreach ($node as $key => $value) {
            if ($key === '$ref' && is_string($value)) {
                $refs[] = $value;
            } elseif (is_array($value)) {
                $refs = [...$refs, ...$this->collectRefs($value)];
            }
        }

        return $refs;
    }
}
