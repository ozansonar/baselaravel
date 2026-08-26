{{-- Newsletter sign-up. Hidden entirely when the setting is off, so a project
     that does not send bulk mail carries no dead form. --}}
@if(\App\Models\Setting::getValue('newsletter_enabled', '1') === '1')
    <div class="footer-newsletter">
        <h5>{{ __('site.newsletter.subscribe') }}</h5>
        <form id="newsletterForm" class="footer-newsletter__form" method="POST"
              action="{{ route('newsletter.subscribe') }}" novalidate>
            @csrf
            <label class="visually-hidden" for="newsletterEmail">{{ __('site.newsletter.email_placeholder') }}</label>
            <div class="input-group">
                <input type="email" class="form-control" id="newsletterEmail" name="email"
                       placeholder="{{ __('site.newsletter.email_placeholder') }}" required autocomplete="email">
                <button class="btn btn-primary" type="submit" id="newsletterSubmit">
                    <i class="fa-solid fa-paper-plane"></i>
                </button>
            </div>
            <small class="footer-newsletter__note d-block mt-2" id="newsletterNote"></small>
        </form>
    </div>
@endif
