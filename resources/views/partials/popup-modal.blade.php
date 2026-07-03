@if(isset($popups) && $popups->isNotEmpty())
    @foreach($popups as $popup)
        <div class="modal fade" id="popupModal{{ $popup->id }}" tabindex="-1"
             aria-labelledby="popupModalTitle{{ $popup->id }}" aria-hidden="true"
             data-popup-id="{{ $popup->id }}">
            <div class="modal-dialog modal-dialog-centered {{ $popup->size->modalClass() }}">
                <div class="modal-content popup-modal">
                    <button type="button" class="popup-modal__close" data-bs-dismiss="modal" aria-label="Kapat">
                        <i class="fa-solid fa-xmark"></i>
                    </button>

                    @if($popup->image)
                        <div class="popup-modal__image-wrap">
                            <img src="{{ upload_url($popup->image, 'lg') }}"
                                 alt="{{ $popup->title }}"
                                 class="popup-modal__image img-fluid"
                                 loading="lazy">
                        </div>
                    @endif

                    <div class="popup-modal__body">
                        <h4 class="popup-modal__title" id="popupModalTitle{{ $popup->id }}">
                            {{ $popup->title }}
                        </h4>

                        @if($popup->description)
                            <p class="popup-modal__desc">{{ $popup->description }}</p>
                        @endif

                        @if($popup->button_text && $popup->button_url)
                            <a href="{{ $popup->button_url }}" class="popup-modal__btn">
                                {{ $popup->button_text }}
                                <i class="fa-solid fa-arrow-right ms-2"></i>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@endif
