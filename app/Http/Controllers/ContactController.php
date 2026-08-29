<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactMessageRequest;
use App\Services\ContactMessageService;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class ContactController extends Controller
{
    public function __construct(
        private readonly ContactMessageService $contactMessageService,
        private readonly SettingService $settingService,
    ) {}

    public function create(): View
    {
        return view('contact', [
            'phone'   => $this->settingService->get('contact_phone'),
            'email'   => $this->settingService->get('contact_email'),
            'address' => $this->settingService->get('contact_address'),
            'workingHoursWeekday'  => $this->settingService->get('working_hours_weekday', '08:00 - 18:00'),
            'workingHoursSaturday' => $this->settingService->get('working_hours_saturday', '09:00 - 16:00'),
            'workingHoursSunday'   => $this->settingService->get('working_hours_sunday', 'Kapalı'),
        ]);
    }

    public function store(StoreContactMessageRequest $request): RedirectResponse
    {
        $this->contactMessageService->create($request->validated());

        return redirect()
            ->route('contact')
            ->with('success', __('site.contact.sent'));
    }
}
