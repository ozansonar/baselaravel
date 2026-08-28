{{--
    Yazının ekleri, tür ailesine göre gruplanmış.

    Ekler yazının diline ait: Türkçe sürümün kırk eki varken İngilizcesinin hiç
    eki olmayabilir — çeviri ayrı bir satır, ek de o satıra bağlı.

    Gruplar tek düz liste olarak basılmıyor. Otuz dosyayı alt alta dizmek
    okunmuyor; görsel ızgarada, video ve ses kendi oynatıcısında, belgeler
    indirilebilir kart olarak duruyor — kullanıcı aradığı türü başlığından
    buluyor.

    @var \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, \App\Models\BlogPostFile>> $attachmentGroups
--}}
@php
    $totalCount = $attachmentGroups->sum(fn ($group) => $group->count());
    $totalBytes = $attachmentGroups->sum(fn ($group) => $group->sum('size'));

    /**
     * Aile başlıkları.
     *
     * Anahtarlar tam yazılıyor; aile adı sona eklenerek kurulmuyor. Birleştirme
     * de çalışırdı ama dil dosyası denetimi (InterfaceTranslationTest) view'deki
     * anahtarları metinden okuyor: kurulan anahtar hiçbir zaman doğrulanamaz ve
     * eksik bir çeviri sayfada ham anahtar olarak görünürdü.
     */
    $kindLabels = [
        'image'        => __('site.attachments.kinds.image'),
        'video'        => __('site.attachments.kinds.video'),
        'audio'        => __('site.attachments.kinds.audio'),
        'pdf'          => __('site.attachments.kinds.pdf'),
        'document'     => __('site.attachments.kinds.document'),
        'spreadsheet'  => __('site.attachments.kinds.spreadsheet'),
        'presentation' => __('site.attachments.kinds.presentation'),
        'archive'      => __('site.attachments.kinds.archive'),
        'other'        => __('site.attachments.kinds.other'),
    ];

    /** Toplam boyutu okunur birime çevirir. */
    $humanTotal = match (true) {
        $totalBytes >= 1_073_741_824 => round($totalBytes / 1_073_741_824, 1) . ' GB',
        $totalBytes >= 1_048_576     => round($totalBytes / 1_048_576, 1) . ' MB',
        $totalBytes >= 1024          => round($totalBytes / 1024) . ' KB',
        default                      => $totalBytes . ' B',
    };
@endphp

<section class="attachments" aria-labelledby="attachmentsTitle">

    <header class="attachments__head">
        <h2 class="attachments__title" id="attachmentsTitle">
            <i class="fa-solid fa-paperclip"></i>{{ __('site.attachments.title') }}
        </h2>
        <p class="attachments__lead">{{ __('site.attachments.lead') }}</p>

        <ul class="attachments__summary">
            <li class="attachments__chip attachments__chip--total">
                <i class="fa-regular fa-folder-open"></i>
                {{ __('site.attachments.count', ['count' => $totalCount]) }}
                <span class="attachments__chip-sep">·</span>
                {{ __('site.attachments.total_size', ['size' => $humanTotal]) }}
            </li>
            @foreach($attachmentGroups as $kindValue => $group)
                @php $kind = \App\Enums\FileKind::from($kindValue); @endphp
                <li class="attachments__chip attachments__chip--{{ $kind->color() }}">
                    <i class="{{ $kind->faIcon() }}"></i>
                    {{ $kindLabels[$kindValue] }}
                    <span class="attachments__chip-count">{{ $group->count() }}</span>
                </li>
            @endforeach
        </ul>
    </header>

    @foreach($attachmentGroups as $kindValue => $group)
        @php $kind = \App\Enums\FileKind::from($kindValue); @endphp

        <div class="attachments__group attachments__group--{{ $kind->color() }}">
            <h3 class="attachments__group-title">
                <i class="{{ $kind->faIcon() }}"></i>
                {{ $kindLabels[$kindValue] }}
                <span class="attachments__group-count">{{ $group->count() }}</span>
            </h3>

            @switch($kindValue)

                {{-- Görseller: ızgara + büyütme. Küçük resim yerine dosyanın
                     kendisi basılıyor çünkü ek olduğu gibi saklanıyor; yükü
                     lazy loading taşıyor. --}}
                @case('image')
                    <div class="att-grid">
                        @foreach($group as $file)
                            <figure class="att-img">
                                <button type="button" class="att-img__btn"
                                        data-att-lightbox
                                        data-src="{{ $file->url() }}"
                                        data-caption="{{ $file->original_name }}"
                                        aria-label="{{ __('site.attachments.preview') }}: {{ $file->original_name }}">
                                    <img src="{{ $file->url() }}" alt="{{ $file->original_name }}" loading="lazy" decoding="async">
                                    <span class="att-img__zoom"><i class="fa-solid fa-up-right-and-down-left-from-center"></i></span>
                                </button>
                                <figcaption class="att-img__cap">
                                    <span class="att-img__name" title="{{ $file->original_name }}">{{ $file->original_name }}</span>
                                    <a href="{{ $file->downloadUrl() }}" class="att-img__dl"
                                       title="{{ __('site.attachments.download') }}"
                                       aria-label="{{ __('site.attachments.download') }}: {{ $file->original_name }}">
                                        <i class="fa-solid fa-download"></i>
                                    </a>
                                </figcaption>
                            </figure>
                        @endforeach
                    </div>
                    @break

                {{-- Video ve ses: sayfadan ayrılmadan dinlenip izlenebilsin.
                     preload="metadata" seçildi; kırk dosyalık bir yazıda
                     "auto" bütün medyayı indirmeye başlardı. --}}
                @case('video')
                    <div class="att-media-list">
                        @foreach($group as $file)
                            <div class="att-media">
                                <video class="att-media__player" controls preload="metadata" playsinline src="{{ $file->url() }}">
                                    {{ __('site.attachments.unsupported') }}
                                </video>
                                @include('partials.blog-attachment-foot', ['file' => $file])
                            </div>
                        @endforeach
                    </div>
                    @break

                @case('audio')
                    <div class="att-media-list">
                        @foreach($group as $file)
                            <div class="att-media att-media--audio">
                                <audio class="att-media__player" controls preload="none" src="{{ $file->url() }}">
                                    {{ __('site.attachments.unsupported') }}
                                </audio>
                                @include('partials.blog-attachment-foot', ['file' => $file])
                            </div>
                        @endforeach
                    </div>
                    @break

                {{-- Belge, tablo, sunum, arşiv: kartın tamamı indirme
                     bağlantısı. Küçük bir simgeyi bulmak zorunda kalmasın. --}}
                @default
                    <div class="att-files">
                        @foreach($group as $file)
                            <a href="{{ $file->downloadUrl() }}" class="att-file">
                                <span class="att-file__icon"><i class="{{ $kind->faIcon() }}"></i></span>
                                <span class="att-file__body">
                                    <span class="att-file__name">{{ $file->original_name }}</span>
                                    <span class="att-file__meta">
                                        <span class="att-file__ext">.{{ $file->extension }}</span>
                                        <span>{{ $file->humanSize() }}</span>
                                    </span>
                                </span>
                                <span class="att-file__action" aria-hidden="true"><i class="fa-solid fa-download"></i></span>
                                <span class="visually-hidden">{{ __('site.attachments.download') }}</span>
                            </a>
                        @endforeach
                    </div>

            @endswitch
        </div>
    @endforeach
</section>
