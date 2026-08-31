{{-- Newsletter sign-up. Hidden entirely when the setting is off, so a project
     that does not send bulk mail carries no dead form. --}}
@if(\App\Models\Setting::getValue('newsletter_enabled', '1') === '1')
    <div class="footer-newsletter">
        <h5>{{ __('site.newsletter.subscribe') }}</h5>
        {{-- data-fv-no-lock: form AJAX ile gidiyor, düğmesini app.js yönetiyor. --}}
        <form id="newsletterForm" class="footer-newsletter__form" method="POST"
              action="{{ route('newsletter.subscribe') }}" data-validate novalidate data-fv-no-lock>
            @csrf
            <label class="visually-hidden" for="newsletterEmail">{{ __('site.newsletter.email_placeholder') }}</label>
            <div class="input-group">
                <input type="text" class="form-control" id="newsletterEmail" name="email"
                       data-validation-engine="validate[required,custom[email],maxSize[191]]"
                       placeholder="{{ __('site.newsletter.email_placeholder') }}" autocomplete="email">
                <button class="btn btn-primary" type="submit" id="newsletterSubmit"
                aria-label="{{ __('site.newsletter.submit_aria') }}">
                    <i class="fa-solid fa-paper-plane"></i>
                </button>
            </div>
            <small class="footer-newsletter__note d-block mt-2" id="newsletterNote"></small>
        </form>
    </div>
@endif
