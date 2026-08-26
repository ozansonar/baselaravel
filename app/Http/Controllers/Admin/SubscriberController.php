<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\SubscriberStatus;
use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use App\Services\LanguageService;
use App\Services\RecipientImportService;
use App\Services\SubscriberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

final class SubscriberController extends Controller
{
    public function __construct(
        private readonly SubscriberService $subscribers,
        private readonly RecipientImportService $importer,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Subscriber::class);

        return view('admin.subscribers.index', [
            'subscribers' => $this->subscribers->paginate(25, $request->only(['status', 'locale', 'search'])),
            'stats'       => $this->subscribers->stats(),
            'statuses'    => SubscriberStatus::cases(),
            'languages'   => app(LanguageService::class)->active(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Subscriber::class);

        $validated = $request->validate([
            'email'  => ['required', 'email', 'max:191'],
            'name'   => ['nullable', 'string', 'max:191'],
            'locale' => ['nullable', 'string', 'size:2'],
        ]);

        $this->subscribers->subscribe(
            $validated['email'],
            $validated['name'] ?? null,
            $validated['locale'] ?? null,
            'panel',
        );

        return back()->with('success', 'Abone eklendi.');
    }

    public function import(Request $request): RedirectResponse
    {
        $this->authorize('create', Subscriber::class);

        $validated = $request->validate([
            'file'   => ['required', 'file', 'mimes:xlsx,xls,ods,csv,txt', 'max:10240'],
            'locale' => ['nullable', 'string', 'size:2'],
        ], [
            'file.mimes' => 'Yalnızca Excel (.xlsx, .xls, .ods) veya CSV dosyası yükleyebilirsiniz.',
        ]);

        try {
            $parsed = $this->importer->parse($request->file('file'));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $result = $this->subscribers->importMany($parsed['rows'], $validated['locale'] ?? null);

        return back()->with('success', sprintf(
            '%d yeni abone eklendi, %d kayıt güncellendi.%s',
            $result['added'],
            $result['updated'],
            $parsed['invalid'] > 0 ? " {$parsed['invalid']} geçersiz adres atlandı." : '',
        ));
    }

    public function unsubscribe(Subscriber $subscriber): RedirectResponse
    {
        $this->authorize('update', $subscriber);

        $this->subscribers->unsubscribeByToken($subscriber->unsubscribe_token);

        return back()->with('success', 'Abonelik durduruldu.');
    }

    public function destroy(Subscriber $subscriber): RedirectResponse
    {
        $this->authorize('delete', $subscriber);

        $subscriber->delete();

        return back()->with('success', 'Abone silindi.');
    }

    public function restore(int $subscriber): RedirectResponse
    {
        $model = Subscriber::withTrashed()->findOrFail($subscriber);

        $this->authorize('delete', $model);

        $model->restore();

        return back()->with('success', 'Abone geri yüklendi.');
    }
}
