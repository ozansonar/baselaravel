<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminNotification;
use App\Services\QueueRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Kuyrukta patlayan bir işin kaydı tutuluyor mu?
 *
 * Çerçevede bu işi `queue:work` yapıyor: `WorkCommand` açılışta `JobFailed`
 * olayına abone olup `queue.failer` sağlayıcısına aktarıyor. Bu proje
 * `queue:work` çalıştıramıyor (pcntl yok — `QueueRunner`'ın var olma sebebi
 * bu), yani o abone hiç kurulmuyordu.
 *
 * `Job::fail()` işi siliyor ve `JobFailed` olayını fırlatıyor ama tabloya
 * yazmıyor. Sonuç: patlayan iş sessizce yok oluyordu, `failed_jobs` her zaman
 * boştu ve bunun üstüne kurulan her şey — Sistem Sağlık ekranındaki sayı,
 * Kuyruk ekranının listesi — sonsuza kadar boş görünecekti.
 */
class FailedJobRecordingTest extends TestCase
{
    use RefreshDatabase;

    private function pushFailingJob(): void
    {
        Queue::connection('database')->push(new PatlayanSinamaIsi());
    }

    public function test_a_job_that_blows_up_lands_in_failed_jobs(): void
    {
        $this->pushFailingJob();

        $result = app(QueueRunner::class)->drain();

        $this->assertSame(1, $result['failed']);
        $this->assertSame(0, $result['processed']);

        $this->assertDatabaseCount('failed_jobs', 1);
    }

    public function test_the_record_carries_the_error_so_the_screen_can_show_it(): void
    {
        $this->pushFailingJob();
        app(QueueRunner::class)->drain();

        $row = DB::table('failed_jobs')->first();

        $this->assertNotNull($row);
        $this->assertSame('default', $row->queue);
        $this->assertStringContainsString('sinama isi patladi', $row->exception);
        $this->assertStringContainsString('PatlayanSinamaIsi', $row->payload);
    }

    /**
     * `QueueRunner` bir işi bir kez çalıştırıyor: patlarsa doğrudan başarısız
     * sayılıyor, yeniden deneme yok. Kaldırılan `telegram_notify_level`
     * ayarının sunduğu "her denemede / son denemede" seçimi bu yüzden
     * karşılığı olmayan bir şeyi anlatıyordu.
     */
    public function test_a_failing_job_is_not_retried(): void
    {
        $this->pushFailingJob();

        app(QueueRunner::class)->drain();

        $this->assertSame(0, DB::table('jobs')->count(), 'İş kuyruğa geri konmuş');
        $this->assertDatabaseCount('failed_jobs', 1);
    }

    /**
     * Bildirim yolu ayrı ve zaten çalışıyor: `QueueRunner` başarısızlıkta
     * `report()` çağırıyor, o da `ExceptionNotifier`'a düşüyor. Kaldırılan
     * ayarın ikinci bir bildirim düğmesi olması bu yüzden gereksizdi.
     */
    public function test_a_failing_job_already_notifies_the_administrator(): void
    {
        $this->pushFailingJob();

        app(QueueRunner::class)->drain();

        $this->assertSame(
            1,
            AdminNotification::where('type', 'exception')->count(),
            'Patlayan iş yöneticiye haber vermedi',
        );
    }

    public function test_a_job_that_succeeds_leaves_no_failure_behind(): void
    {
        Queue::connection('database')->push(new CalisanSinamaIsi());

        $result = app(QueueRunner::class)->drain();

        $this->assertSame(1, $result['processed']);
        $this->assertDatabaseCount('failed_jobs', 0);
        $this->assertSame(0, DB::table('jobs')->count());
    }
}

final class PatlayanSinamaIsi implements ShouldQueue
{
    use Dispatchable;

    public function handle(): void
    {
        throw new \RuntimeException('sinama isi patladi');
    }
}

final class CalisanSinamaIsi implements ShouldQueue
{
    use Dispatchable;

    public function handle(): void
    {
        // Bir şey yapmıyor; işin sorunsuz tamamlanması sınanıyor.
    }
}
