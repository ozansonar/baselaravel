{{--
    Galeri karesinin görseli.

    Fotoğraf ve video kutuları aynı görseli aynı kurallarla basıyor; ayrı ayrı
    yazılsaydı biri değişip öteki geride kalırdı.

    Görsel görünür alana girene kadar inmiyor. Tek istisna ilk sayfanın ilk iki
    karesi: onlar zaten açılışta ekranda, geciktirilmeleri sayfanın en büyük
    görselinin boyanmasını geciktirmek olurdu.

    $item  GalleryItem
    $eager bool
--}}
@if($item->image)
    <img src="{{ upload_url($item->image, 'md') }}"
         @if($srcset = upload_srcset($item->image))
             srcset="{{ $srcset }}"
             sizes="(max-width: 576px) 100vw, (max-width: 992px) 50vw, 33vw"
         @endif
         alt="{{ $item->title }}"
         class="gallery-item__img"
         loading="{{ $eager ? 'eager' : 'lazy' }}"
         decoding="{{ $eager ? 'sync' : 'async' }}"
         @if($eager) fetchpriority="high" @endif>
@else
    <span class="post-card__ph"><i class="fa-regular fa-image"></i></span>
@endif
