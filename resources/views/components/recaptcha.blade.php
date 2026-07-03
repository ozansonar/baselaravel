@props(['hideError' => false])

@php
    $recaptchaService = app(\App\Services\RecaptchaService::class);
@endphp

@if($recaptchaService->isEnabled())
<div class="g-recaptcha mb-3" data-sitekey="{{ $recaptchaService->siteKey() }}"></div>
@if(!$hideError)
@error('g-recaptcha-response')
<div class="text-danger small mb-3">{{ $message }}</div>
@enderror
@endif
@endif
