<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\HelpService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Yardım merkezi.
 *
 * Yetki istemiyor: panele girebilen herkes panelin nasıl çalıştığını
 * okuyabilmeli. Sayfa hiçbir veri göstermiyor, yalnız anlatıyor.
 */
final class HelpController extends Controller
{
    public function __construct(
        private readonly HelpService $help,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $category = (string) $request->query('category', 'all');

        return view('admin.help.index', [
            'search'      => $search,
            'category'    => $category,
            'guides'      => $this->help->guides($search !== '' ? $search : null),
            'faqs'        => $this->help->faqs($search !== '' ? $search : null, $category),
            'categories'  => $this->help->faqCategories(),
            'stats'       => $this->help->stats(),
            'environment' => $this->help->environment(),
            'supportEmail' => (string) Setting::getValue('contact_email', ''),
            'supportPhone' => (string) Setting::getValue('contact_phone', ''),
        ]);
    }
}
