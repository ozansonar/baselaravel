{{--
    One language's file attachments — blog yazısı ve sayfa aynı bölümü kullanır.

    Rendered once per language tab, so the Turkish version can carry forty files
    while the English one carries none — an attachment belongs to that language's
    row, not to the translation group.

    Dosyalar formla birlikte gitmiyor; her biri kendi isteğiyle yükleniyor.
    Hepsi tek POST'ta gitseydi gövde post_max_size'ı aşar, PHP gövdeyi komple
    atar ve CSRF alanı da onunla gittiği için istek 419 dönerdi: kullanıcı
    yazdığı içeriği kaybederdi.

    @var \App\Models\Language $language
    @var \Illuminate\Database\Eloquent\Model|null $translation Bu dilin kayıtlı satırı
    @var \App\Enums\AttachableContent $attachableType
    @var array{per_file: int, post_max: int, max_files: int} $fileLimits
    @var array<string, \Illuminate\Support\Collection<int, \App\Models\ContentFile>> $pendingFiles
--}}
@php
    /** Bayt tavanını kullanıcının okuduğu birime çevirir. */
    $humanLimit = function (int $bytes): string {
        return $bytes >= 1_073_741_824
            ? round($bytes / 1_073_741_824, 1) . ' GB'
            : ($bytes >= 1_048_576 ? round($bytes / 1_048_576) . ' MB' : round($bytes / 1024) . ' KB');
    };

    $files = $translation?->files ?? collect();

    /**
     * Doğrulama hatasından sonra forma geri dönen bekleyen ekler.
     *
     * Yükleme satırlarını JS çiziyor; sayfa yeniden yüklenince kayboluyorlar.
     * Başlığı boş bırakıp kaydeden kullanıcı, hatayı düzeltip tekrar
     * kaydettiğinde az önce yüklediği beş dosyayı listede bulamıyordu.
     */
    $pending = $pendingFiles[$language->code] ?? collect();
@endphp

<div class="card-dark mb-4" id="section-files_{{ $language->code }}">
  <div class="card-header-custom">
    <div class="form-section-header mb-0">
      <div class="form-section-icon bg-icon-pink"><i class="bi bi-paperclip"></i></div>
      <div>
        <h6 class="mb-0">Dosya Ekleri</h6>
        <small class="text-muted">Bu dile ait görsel, video, ses, PDF, Excel ve sunum dosyaları</small>
      </div>
    </div>
  </div>
  <div class="card-body-custom">

    {{-- Yükleme alanı ile liste tek kapsayıcıda: JS her dil sekmesi için ayrı
         bir Dropzone kuruyor ve ihtiyacı olan her adresi buradan okuyor. --}}
    <div class="bpf"
         data-bpf
         data-locale="{{ $language->code }}"
         data-attachable-type="{{ $attachableType->value }}"
         data-attachable-id="{{ $translation?->getKey() }}"
         data-upload-url="{{ route('admin.content-files.upload') }}"
         data-discard-url="{{ route('admin.content-files.discard', ['token' => 'TOKEN']) }}"
         data-destroy-url="{{ route('admin.content-files.destroy', ['file' => 'FILE_ID']) }}"
         data-max-bytes="{{ $fileLimits['per_file'] }}"
         data-max-label="{{ $humanLimit($fileLimits['per_file']) }}"
         data-accept="{{ \App\Http\Requests\Admin\StoreContentFileRequest::acceptAttribute() }}">

      <div class="bpf-dz" data-bpf-dropzone>
        <div class="dz-message bpf-dz__message">
          <div class="bpf-dz__icon"><i class="bi bi-cloud-arrow-up"></i></div>
          <div>
            <p class="bpf-dz__title">Dosyaları buraya sürükle veya <u>bilgisayarından seç</u></p>
            <p class="bpf-dz__hint">
              Sayı sınırı yok · dosya başına en fazla {{ $humanLimit($fileLimits['per_file']) }} ·
              yüklenen dosya <strong>{{ $language->name }}</strong> içeriğine bağlanır
            </p>
            <ul class="bpf-dz__chips">
              <li><i class="bi bi-file-earmark-image"></i>JPG · PNG · WebP · GIF</li>
              <li><i class="bi bi-file-earmark-pdf"></i>PDF</li>
              <li><i class="bi bi-file-earmark-spreadsheet"></i>XLS · XLSX · CSV</li>
              <li><i class="bi bi-file-earmark-word"></i>DOC · DOCX · TXT</li>
              <li><i class="bi bi-file-earmark-slides"></i>PPT · PPTX</li>
              <li><i class="bi bi-file-earmark-play"></i>MP4 · MOV · MP3</li>
              <li><i class="bi bi-file-earmark-zip"></i>ZIP · RAR</li>
            </ul>
          </div>
        </div>
      </div>

      {{-- Kayıtlı ekler ve yeni yüklenenler tek liste: numara ikisi boyunca
           kesintisiz akıyor ve sıra ön yüzde göründükleri sırayla aynı. --}}
      <div class="bpf-list" data-bpf-list>
        @foreach($files as $file)
          @include('admin.partials.content-file-row', ['file' => $file, 'locale' => $language->code])
        @endforeach
        @foreach($pending as $file)
          @include('admin.partials.content-file-row', ['file' => $file, 'locale' => $language->code])
        @endforeach
      </div>

      <p class="bpf-empty {{ $files->isEmpty() && $pending->isEmpty() ? '' : 'd-none' }}" data-bpf-empty>
        <i class="bi bi-inbox"></i>Bu dilde henüz ek yok.
      </p>

      @if($translation === null)
        {{-- Bu dilin satırı yok: ek bağlanacak bir yer bulamaz. Dosya
             belirteciyle bekliyor, satır doğduğunda iliştiriliyor. --}}
        <p class="bpf-note">
          <i class="bi bi-info-circle"></i>
          Bu dilde henüz içerik yok. Şimdi yüklediğin dosyalar, bu sekmedeki
          alanları doldurup kaydettiğinde içeriğe bağlanır.
        </p>
      @endif
    </div>
  </div>
</div>
