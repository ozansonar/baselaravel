<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Support\Seo\SeoReport;
use App\Support\Seo\SeoSubject;
use Illuminate\Support\Facades\Log;

/**
 * Denetimi yürüten motor.
 *
 * Kayıtlı kuralları sırayla çalıştırıp bulguları tek rapora topluyor. Yaptığı
 * iş bu kadar — kuralların ne baktığını bilmiyor, yalnız hangilerinin kayıtlı
 * olduğunu (`config/seo.php`) biliyor.
 *
 * Bir kuralın patlaması bütün denetimi düşürmüyor: denetim bir kolaylık, form
 * kaydetmenin şartı değil. Bir kural hata verirse o kural atlanıyor, kalanlar
 * çalışıyor ve sebep loga düşüyor. Tersi olsaydı bozuk bir kural, yazarın
 * sayfayı kaydetmesini engellerdi.
 */
final class SeoAuditor
{
    public function audit(SeoSubject $subject): SeoReport
    {
        $issues = [];

        foreach ($this->checks() as $check) {
            try {
                foreach ($check->run($subject) as $issue) {
                    $issues[] = $issue;
                }
            } catch (\Throwable $e) {
                Log::warning('SEO kuralı çalışmadı', [
                    'check' => $check::class,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return SeoReport::fromIssues($issues);
    }

    /**
     * Kayıtlı kurallar.
     *
     * @return list<SeoCheck>
     */
    public function checks(): array
    {
        /** @var list<class-string<SeoCheck>> $classes */
        $classes = config('seo.checks', []);

        $checks = [];

        foreach ($classes as $class) {
            if (! class_exists($class) || ! is_a($class, SeoCheck::class, true)) {
                Log::warning('Tanımsız SEO kuralı yok sayıldı', ['check' => $class]);

                continue;
            }

            $checks[] = app($class);
        }

        return $checks;
    }
}
