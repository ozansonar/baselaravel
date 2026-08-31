<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * Formun HTTP yöntemi rotasının kabul ettiğiyle uyuşuyor mu?
 *
 * Tarayıcı yalnızca GET ve POST gönderebiliyor; PUT/PATCH/DELETE bekleyen bir
 * rotaya form göndermek için gizli `_method` alanı (@method) gerekiyor. Alan
 * yoksa istek POST olarak gidiyor ve rota 405 döndürüyor — form açılıyor,
 * dolduruluyor, "kaydet"e basılıyor ve karşıya hata sayfası çıkıyor.
 *
 * Bu kolayca gözden kaçıyor: @csrf ile @method yan yana duruyor, biri silinince
 * form görünüşte sağlam kalıyor. Slider, galeri ve popup düzenleme formlarında
 * tam olarak bu oldu — formlar dil sekmelerine taşınırken @method satırı düştü
 * ve üç modülde de düzenleme kaydedilemez hale geldi.
 *
 * Denetim rota adına bakıp tahmin yürütmüyor, rotayı yönlendiriciden bulup
 * hangi yöntemleri kabul ettiğini soruyor: adı ".update" ile biten ama gerçekten
 * POST kabul eden rotalar (şifre sıfırlama gibi) yanlışlıkla suçlanmasın.
 */
final class FormMethodSpoofingTest extends TestCase
{
    public function test_every_post_form_carries_the_method_its_route_expects(): void
    {
        $sorunlu = [];

        foreach ($this->bladeFiles() as $file) {
            $contents = file_get_contents($file);

            if ($contents === false) {
                continue;
            }

            foreach ($this->formsIn($contents) as $form) {
                // GET formları yöntem taklidi kullanmıyor.
                if (! preg_match('/method\s*=\s*["\']post["\']/i', $form)) {
                    continue;
                }

                $route = $this->routeOf($form);

                // Adresi değişkenden gelen ya da adı çözülemeyen form: burada
                // söylenecek bir şey yok, sessizce geçiliyor.
                if ($route === null) {
                    continue;
                }

                $methods = $this->methodsFor($route);

                if (in_array('POST', $methods, true)) {
                    continue;
                }

                if ($this->declaresOneOf($form, $methods)) {
                    continue;
                }

                $sorunlu[] = sprintf(
                    '%s → %s rotası %s kabul ediyor ama form @method taşımıyor',
                    $this->relative($file),
                    $route->getName(),
                    implode('|', $methods),
                );
            }
        }

        $this->assertSame([], $sorunlu, "Yöntemi eksik form:\n" . implode("\n", $sorunlu));
    }

    /**
     * Adresin kabul ettiği bütün yöntemler.
     *
     * Aynı adres birden çok kayıtla tanımlanabiliyor ve bunların yalnız biri
     * ad taşıyor: /giris hem GET (formu gösteren, "login" adlı) hem POST
     * (formu alan, adsız) olarak kayıtlı. Yalnız adlı kaydın yöntemlerine
     * bakılsaydı forma haksız yere "POST kabul edilmiyor" denirdi.
     *
     * @return list<string>
     */
    private function methodsFor(Route $route): array
    {
        $methods = [];

        foreach (RouteFacade::getRoutes() as $candidate) {
            if ($candidate->uri() === $route->uri()) {
                $methods = array_merge($methods, $candidate->methods());
            }
        }

        return array_values(array_diff(array_unique($methods), ['HEAD']));
    }

    /** Formun action'ındaki route('...') adını yönlendiricide arar. */
    private function routeOf(string $form): ?Route
    {
        if (! preg_match('/action\s*=\s*["\'][^"\']*route\(\s*["\']([a-zA-Z0-9._-]+)["\']/', $form, $match)) {
            return null;
        }

        return RouteFacade::getRoutes()->getByName($match[1]);
    }

    /**
     * Blade dosyasındaki her <form> ... </form> bloğu.
     *
     * @return list<string>
     */
    private function formsIn(string $contents): array
    {
        preg_match_all('/<form\b.*?<\/form>/is', $contents, $matches);

        return $matches[0];
    }

    /**
     * @param list<string> $methods
     */
    private function declaresOneOf(string $form, array $methods): bool
    {
        foreach ($methods as $method) {
            if (preg_match('/@method\(\s*["\']' . $method . '["\']\s*\)/i', $form)) {
                return true;
            }

            // Yöntem satır içi gizli alanla da yazılabiliyor.
            if (preg_match('/name\s*=\s*["\']_method["\'][^>]*value\s*=\s*["\']' . $method . '["\']/i', $form)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function bladeFiles(): array
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS),
        );

        $files = [];

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function relative(string $path): string
    {
        return str_replace(base_path() . '/', '', $path);
    }
}
