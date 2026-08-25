@if(isset($popups) && $popups->isNotEmpty())
    @foreach($popups as $popup)
        <div class="modal fade site-popup" id="popupModal{{ $popup->id }}" tabindex="-1"
             aria-labelledby="popupTitle{{ $popup->id }}" aria-hidden="true"
             data-popup-id="{{ $popup->id }}">
            <div class="modal-dialog modal-dialog-centered {{ $popup->size->modalClass() }}">
                <div class="modal-content site-popup__content">
                    <button type="button" class="site-popup__close" data-bs-dismiss="modal" aria-label="{{ __('site.actions.close') }}">
                        <i class="fa-solid fa-xmark"></i>
                    </button>

                    @if($popup->image)
                        <img src="{{ upload_url($popup->image, 'lg') }}" alt="{{ $popup->title }}"
                             class="site-popup__img" loading="lazy" decoding="async">
                    @endif

                    <div class="site-popup__body">
                        <h4 class="site-popup__title" id="popupTitle{{ $popup->id }}">{{ $popup->title }}</h4>

                        @if($popup->description)
                            <p class="site-popup__desc">{{ $popup->description }}</p>
                        @endif

                        @if($popup->button_text && $popup->button_url)
                            <a href="{{ $popup->button_url }}" class="btn btn-primary">
                                {{ $popup->button_text }} <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@endif
