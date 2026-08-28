{{--
    Galeri büyütme penceresi.

    Sayfada tek bir kap duruyor; hangi ızgaraya basıldıysa onun görselleriyle
    doldurulup açılıyor. Etiketler sunucudan geliyor — JS içine gömülü metin
    İngilizce sayfada Türkçe kalırdı.

    Sürücü: public/js/app.js → lightbox bölümü.
--}}
<div class="lightbox" data-lightbox hidden>
    <button type="button" class="lightbox__btn lightbox__close" data-lightbox-close
            aria-label="{{ __('site.attachments.close') }}">
        <i class="fa-solid fa-xmark"></i>
    </button>
    <button type="button" class="lightbox__btn lightbox__prev" data-lightbox-prev
            aria-label="{{ __('site.attachments.prev') }}">
        <i class="fa-solid fa-chevron-left"></i>
    </button>
    <button type="button" class="lightbox__btn lightbox__next" data-lightbox-next
            aria-label="{{ __('site.attachments.next') }}">
        <i class="fa-solid fa-chevron-right"></i>
    </button>

    <figure class="lightbox__figure">
        <img src="" alt="" class="lightbox__img" data-lightbox-img>
        <figcaption class="lightbox__caption" data-lightbox-caption></figcaption>
    </figure>
</div>
