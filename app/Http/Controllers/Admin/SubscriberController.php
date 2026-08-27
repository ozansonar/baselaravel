<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\SubscriberSource;
use App\Enums\SubscriberStatus;
use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use App\Models\SubscriberList;
use App\Services\LanguageService;
use App\Services\RecipientImportService;
use App\Services\SubscriberListService;
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
        private readonly SubscriberListService $lists,
    ) {}

    /**
     * Listede gösterilebilecek kayıt sayıları; istekten gelen değer bu kümeyle
     * sınırlı, aksi hâlde tek istekle tüm tablo çekilebilirdi.
     */
    private const PER_PAGE_OPTIONS = [25, 50, 100];

    /**
     * @var array<string, string>
     */
    private const SORT_OPTIONS = [
        'recent' => 'En yeni kayıt',
        'oldest' => 'En eski kayıt',
        'email'  => 'E-postaya göre (A-Z)',
    ];

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Subscriber::class);

        $perPage = (int) $request->input('per_page', 25);
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 25;

        $sort = $request->string('sort')->value();
        $sort = array_key_exists($sort, self::SORT_OPTIONS) ? $sort : '';

        $filters = [
            'search'   => $request->string('search')->trim()->value(),
            'status'   => $request->string('status')->value(),
            'source'   => $request->string('source')->value(),
            'locale'   => $request->string('locale')->value(),
            'list_id'  => $request->string('list_id')->value(),
            'unlisted' => $request->boolean('unlisted') ? '1' : '',
            'from'     => $request->string('from')->value(),
            'to'       => $request->string('to')->value(),
            'sort'     => $sort,
        ];

        $lists = $this->lists->all();

        return view('admin.subscribers.index', [
            'subscribers'    => $this->subscribers->paginate($perPage, $filters),
            'stats'          => $this->subscribers->stats(),
            'statuses'       => SubscriberStatus::cases(),
            'sources'        => SubscriberSource::cases(),
            'languages'      => app(LanguageService::class)->active(),
            'lists'          => $lists,
            'activeList'     => $request->integer('list_id') ?: null,
            'defaultList'    => $lists->firstWhere('is_default', true),
            'filters'        => $filters,
            'perPage'        => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'sortOptions'    => self::SORT_OPTIONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Subscriber::class);

        $validated = $request->validate([
            'email'      => ['required', 'email', 'max:191'],
            'first_name' => ['nullable', 'string', 'max:191'],
            'last_name'  => ['nullable', 'string', 'max:191'],
            'locale'     => ['nullable', 'string', 'size:2'],
            'list_ids'   => ['nullable', 'array'],
            'list_ids.*' => ['integer', 'exists:subscriber_lists,id'],
        ]);

        $this->subscribers->subscribe(
            $validated['email'],
            $validated['first_name'] ?? null,
            $validated['last_name'] ?? null,
            $validated['locale'] ?? null,
            'panel',
            $validated['list_ids'] ?? [],
        );

        return back()->with('success', 'Abone eklendi.');
    }

    public function import(Request $request): RedirectResponse
    {
        $this->authorize('create', Subscriber::class);

        $validated = $request->validate([
            'file'       => ['required', 'file', 'mimes:xlsx,xls,ods,csv,txt', 'max:10240'],
            'locale'     => ['nullable', 'string', 'size:2'],
            'list_ids'   => ['nullable', 'array'],
            'list_ids.*' => ['integer', 'exists:subscriber_lists,id'],
        ], [
            'file.mimes' => 'Yalnızca Excel (.xlsx, .xls, .ods) veya CSV dosyası yükleyebilirsiniz.',
        ]);

        try {
            $parsed = $this->importer->parse($request->file('file'));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $result = $this->subscribers->importMany(
            $parsed['rows'],
            $validated['locale'] ?? null,
            'import',
            $validated['list_ids'] ?? [],
        );

        return back()->with('success', sprintf(
            '%d yeni abone eklendi, %d kayıt güncellendi.%s',
            $result['added'],
            $result['updated'],
            $parsed['invalid'] > 0 ? " {$parsed['invalid']} geçersiz adres atlandı." : '',
        ));
    }

    /**
     * Seçilen aboneleri bir listeye ekler ya da listeden çıkarır.
     *
     * Elle tek tek düzenlemek yerine toplu iş: mevcut bir aboneyi yeni açılan
     * "Tedarikçiler" listesine taşımanın başka yolu yok.
     */
    public function bulkList(Request $request): RedirectResponse
    {
        $this->authorize('manageLists', Subscriber::class);

        $validated = $request->validate([
            'list_id'          => ['required', 'integer', 'exists:subscriber_lists,id'],
            'action'           => ['required', 'in:add,remove'],
            'subscriber_ids'   => ['required', 'array', 'min:1'],
            'subscriber_ids.*' => ['integer', 'exists:subscribers,id'],
        ], [
            'subscriber_ids.required' => 'Önce en az bir abone seçin.',
        ]);

        $list = SubscriberList::findOrFail($validated['list_id']);

        if ($validated['action'] === 'add') {
            $added = $this->lists->addMany($list, $validated['subscriber_ids']);

            return back()->with('success', sprintf('%d abone "%s" listesine eklendi.', $added, $list->name));
        }

        $removed = $this->lists->removeMany($list, $validated['subscriber_ids']);

        return back()->with('success', sprintf('%d abone "%s" listesinden çıkarıldı.', $removed, $list->name));
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
