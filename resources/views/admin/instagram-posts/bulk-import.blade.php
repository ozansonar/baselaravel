@extends('layouts.admin')

@section('title', 'Toplu Plan — Instagram Gönderileri')
@section('page_title', 'Toplu Plan')
@section('page_description', 'Excel ile birden çok Instagram gönderisini tek seferde planla.')

@section('content')

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('admin.instagram-posts.index') }}" class="breadcrumb-link"><i class="bi bi-instagram me-1"></i>Instagram Gönderileri</a>
            </li>
            <li class="breadcrumb-item active text-teal">Toplu Plan</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-start align-items-sm-center justify-content-between flex-column flex-sm-row gap-3 mb-4">
        <div>
            <h1 class="page-title">Toplu Plan</h1>
            <p class="page-subtitle">Excel dosyası ile bir kerede max {{ $maxRows }} post planla. <strong>3 yol var:</strong>
                <a href="{{ route('admin.image-library.index') }}" class="text-teal">Kütüphane (önerilen — $0 maliyet)</a> ·
                AI Prompt (ChatGPT/Gemini) · Manuel Excel.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn-teal" data-bs-toggle="modal" data-bs-target="#bulkLibraryPlanModal"
                    title="Kütüphaneden otomatik plan oluştur — AI maliyeti $0">
                <i class="bi bi-images me-1"></i> Kütüphaneden Plan Oluştur
            </button>
            <button type="button" class="btn-glass" data-bs-toggle="modal" data-bs-target="#bulkAiPromptModal" title="ChatGPT/Gemini'ye verebileceğin hazır prompt">
                <i class="bi bi-stars me-1"></i> AI Prompt'u
            </button>
            <a href="{{ route('admin.instagram-posts.bulk.template') }}" class="btn-glass">
                <i class="bi bi-download me-1"></i> Excel Şablon İndir
            </a>
        </div>
    </div>

    {{-- Hızlı başlangıç: Excel hazırlama 3 yolu --}}
    <div class="bulk-quickstart-card mb-4">
        <div class="bulk-quickstart-header">
            <i class="bi bi-lightbulb-fill text-warning"></i>
            <strong>Excel dosyanı 3 yoldan biriyle hazırla</strong>
            <small class="text-muted">— hangisi sana kolaysa</small>
        </div>
        <div class="row g-3 mt-2">
            <div class="col-md-4">
                <div class="bulk-quickstart-step">
                    <div class="bulk-quickstart-num">1</div>
                    <div>
                        <strong><i class="bi bi-stars text-warning me-1"></i> AI ile hazırla</strong>
                        <span class="badge bg-warning-subtle text-warning-emphasis ms-1">EN KOLAY</span>
                        <small class="d-block text-muted mt-1">
                            Üstteki <strong>"AI Prompt'u"</strong> butonuna tıkla → hazır promptu kopyala → Gemini/ChatGPT'ye ver → çıktıyı Excel'e yapıştır.
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="bulk-quickstart-step">
                    <div class="bulk-quickstart-num">2</div>
                    <div>
                        <strong><i class="bi bi-download text-teal me-1"></i> Şablonu indir</strong>
                        <small class="d-block text-muted mt-1">
                            <strong>"Excel Şablon İndir"</strong> ile boş şablonu al → Excel'de elle doldur.
                            10 sütun (A-J) örnek satırlarla hazır.
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="bulk-quickstart-step">
                    <div class="bulk-quickstart-num">3</div>
                    <div>
                        <strong><i class="bi bi-cloud-upload text-info me-1"></i> Yükle ve başlat</strong>
                        <small class="d-block text-muted mt-1">
                            Hazırladığın <code>.xlsx</code> dosyasını aşağıdaki <strong>"Excel + ZIP Yükle"</strong> alanına bırak → Yükle ve İşlemeye Başla.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Validation hataları (genel) --}}
    @if(session('bulk_errors'))
    <div class="card-dark mb-4">
        <div class="card-body-custom">
            <h6 class="text-danger mb-3"><i class="bi bi-exclamation-triangle me-1"></i> Satır Hataları</h6>
            <ul class="mb-0 small">
                @foreach(session('bulk_errors') as $err)
                    <li>Satır <strong>{{ $err['row'] }}</strong>: {{ $err['message'] }}</li>
                @endforeach
            </ul>
        </div>
    </div>
    @endif

    @if($errors->any())
    <div class="card-dark mb-4">
        <div class="card-body-custom">
            <h6 class="text-danger mb-3"><i class="bi bi-exclamation-triangle me-1"></i> Form Hataları</h6>
            <ul class="mb-0 small">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
    @endif

    <div class="row g-4">

        {{-- SOL: Yükleme formu --}}
        <div class="col-lg-7">
            <div class="card-dark">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-blue"><i class="bi bi-upload"></i></div>
                        <div>
                            <h6 class="mb-0">Excel + ZIP Yükle</h6>
                            <small class="text-muted">Excel (.xlsx) dosyası zorunlu. ZIP sadece <code>upload</code> mode için gerekli.</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <form method="POST" action="{{ route('admin.instagram-posts.bulk.upload') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">Excel Dosyası <span class="text-danger">*</span></label>
                            <input type="file" name="excel_file" id="bulkExcelFile"
                                   accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                   class="form-control" required>
                            <div class="d-flex justify-content-between align-items-center mt-1 flex-wrap gap-2">
                                <small class="form-text text-muted mb-0">.xlsx formatında Excel dosyası. Max 5 MB.</small>
                                <button type="button" class="btn-glass btn-glass-sm" id="bulkPreviewBtn"
                                        data-bs-toggle="modal" data-bs-target="#bulkPreviewModal"
                                        disabled
                                        title="Yüklemeden önce ilk 10 satırı + validation sonuçlarını gör">
                                    <i class="bi bi-eye me-1"></i> Önizle
                                </button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">ZIP Arşivi (opsiyonel)</label>
                            <input type="file" name="zip_file" accept=".zip" class="form-control">
                            <small class="form-text text-muted">
                                Sadece <code>gorsel_kaynagi=upload</code> kullanılan satırlar için.
                                Görsel/video dosyaları ZIP içine doğrudan koy (klasör yok).
                                Max 100 MB.
                            </small>
                        </div>

                        {{-- AI Görsel Prompt Template (opsiyonel — form-level default) --}}
                        <div class="mb-3">
                            <label class="form-label">AI Görsel Prompt Template (opsiyonel)</label>
                            <select name="prompt_template_id" class="form-control">
                                <option value="">— Template kullanma (raw prompt) —</option>
                                @foreach($promptTemplates ?? [] as $tpl)
                                    <option value="{{ $tpl->id }}">
                                        {{ $tpl->name }}
                                        @if($tpl->reference_image_path) 🖼️ @endif
                                    </option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">
                                Tüm <code>gorsel_kaynagi=ai</code> satırları için varsayılan template.
                                Excel'de <code>prompt_template</code> kolonu doldurulmuşsa o satıra özel template kullanılır (per-row override).
                                <a href="{{ route('admin.ai-images.index') }}" class="text-teal">Templates'i yönet →</a>
                            </small>
                        </div>

                        <button type="submit" class="btn-teal">
                            <i class="bi bi-rocket-takeoff me-1"></i> Yükle ve İşlemeye Başla
                        </button>
                    </form>
                </div>
            </div>

            {{-- ════════ ALTERNATİF: AI Çıktısı Yapıştır → Direkt İçe Aktar ════════ --}}
            <div class="card-dark mt-4 bulk-tsv-card">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-purple"><i class="bi bi-clipboard-plus"></i></div>
                        <div>
                            <h6 class="mb-0">Alternatif: AI Çıktısını Direkt Yapıştır</h6>
                            <small class="text-muted">Excel açma, Text-to-Columns ve dosya yükleme adımlarını atla.</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div class="alert alert-info small mb-3">
                        <strong><i class="bi bi-lightbulb me-1"></i> Nasıl çalışır?</strong>
                        Yukarıdaki <strong>AI Prompt</strong> butonuyla ürettiğin promptu Gemini/ChatGPT'ye yapıştır,
                        AI'ın verdiği TSV tabloyu kopyala ve aşağıya yapıştır. Tab/boşluk/markdown — hepsini otomatik çözeriz.
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="bulkTsvInput">
                            AI Çıktısı (TSV / SSV / Markdown tablosu)
                            <span class="text-danger">*</span>
                        </label>
                        <textarea id="bulkTsvInput" class="form-control bulk-tsv-input"
                                  rows="10"
                                  placeholder="Buraya Gemini/ChatGPT'den kopyaladığın tabloyu yapıştır.

Örnek (header dahil):
tarih	saat	tip	konu	caption	hashtags	gorsel_kaynagi	gorsel_degeri	facebook	prompt_template
2026-01-01	09:00	story	Yılbaşı	...	yılbaşı,doğal	panel		hayir	Özel Gün Hikaye
..."></textarea>
                        <div class="d-flex justify-content-between align-items-center mt-1 flex-wrap gap-2">
                            <small class="form-text text-muted mb-0">
                                <i class="bi bi-info-circle me-1"></i>
                                İlk satır header (tarih, saat, tip, ...). Tab yerine boşlukla gelmiş bile olsa çözülür.
                            </small>
                            <small class="form-text text-muted mb-0" id="bulkTsvCharCount">0 karakter</small>
                        </div>
                    </div>

                    <div class="d-flex gap-2 flex-wrap bulk-tsv-actions">
                        <button type="button" class="btn-glass" id="bulkTsvPreviewBtn"
                                data-bs-toggle="modal" data-bs-target="#bulkPreviewModal"
                                disabled
                                title="İlk 10 satır + validation sonuçlarını gör">
                            <i class="bi bi-eye me-1"></i> Önizle
                        </button>

                        <form method="POST" action="{{ route('admin.instagram-posts.bulk.from-tsv.download') }}"
                              class="d-inline" id="bulkTsvDownloadForm">
                            @csrf
                            <input type="hidden" name="tsv" id="bulkTsvDownloadField">
                            <button type="submit" class="btn-glass" id="bulkTsvDownloadBtn" disabled>
                                <i class="bi bi-file-earmark-excel me-1"></i> .xlsx Olarak İndir
                            </button>
                        </form>

                        <form method="POST" action="{{ route('admin.instagram-posts.bulk.from-tsv.import') }}"
                              class="d-inline" id="bulkTsvImportForm">
                            @csrf
                            <input type="hidden" name="tsv" id="bulkTsvImportField">
                            <select name="prompt_template_id" class="form-control form-control-sm bulk-tsv-template-select"
                                    title="Tüm gorsel_kaynagi=ai satırları için varsayılan template">
                                <option value="">— Template seçme —</option>
                                @foreach($promptTemplates ?? [] as $tpl)
                                    <option value="{{ $tpl->id }}">{{ $tpl->name }}@if($tpl->reference_image_path) 🖼️ @endif</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn-teal" id="bulkTsvImportBtn" disabled>
                                <i class="bi bi-rocket-takeoff me-1"></i> Doğrudan İçe Aktar
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- CSV format açıklaması --}}
            <div class="card-dark mt-4">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-purple"><i class="bi bi-file-earmark-spreadsheet"></i></div>
                        <div>
                            <h6 class="mb-0">Excel Sütunları</h6>
                            <small class="text-muted">Sadece <strong>tarih</strong> zorunlu. Boş bırakılan alanlar için akıllı varsayılanlar.</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div class="table-responsive">
                        <table class="cl-table">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width:48px;">#</th>
                                    <th>Sütun</th>
                                    <th>Boşsa</th>
                                    <th>Açıklama</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="text-center"><strong>A</strong></td>
                                    <td><code>tarih</code> <span class="text-danger">*</span></td>
                                    <td>—</td>
                                    <td>YYYY-MM-DD (örn. 2026-05-01)</td>
                                </tr>
                                <tr>
                                    <td class="text-center"><strong>B</strong></td>
                                    <td><code>saat</code></td>
                                    <td><code>19:00</code></td>
                                    <td>HH:MM (örn. 14:00)</td>
                                </tr>
                                <tr>
                                    <td class="text-center"><strong>C</strong></td>
                                    <td><code>tip</code></td>
                                    <td><code>image</code></td>
                                    <td><code>image</code> | <code>reels</code> | <code>story</code></td>
                                </tr>
                                <tr>
                                    <td class="text-center"><strong>D</strong></td>
                                    <td><code>konu</code></td>
                                    <td>—</td>
                                    <td>AI caption ve görsel için bağlam (panel modu hariç opsiyonel)</td>
                                </tr>
                                <tr>
                                    <td class="text-center"><strong>E</strong></td>
                                    <td><code>caption</code></td>
                                    <td>AI üretir<br><small class="text-muted">(panel: boş kalır)</small></td>
                                    <td>Manuel yazı; boş ise konu'dan AI üretir</td>
                                </tr>
                                <tr>
                                    <td class="text-center"><strong>F</strong></td>
                                    <td><code>hashtags</code></td>
                                    <td>AI üretir<br><small class="text-muted">(panel: boş kalır)</small></td>
                                    <td>Virgülle ayrılmış (örn. <code>doğal,çiftlik</code>)</td>
                                </tr>
                                <tr>
                                    <td class="text-center"><strong>G</strong></td>
                                    <td><code>gorsel_kaynagi</code></td>
                                    <td><code class="text-teal">panel</code></td>
                                    <td>
                                        <strong>panel</strong> <span class="badge bg-secondary">varsayılan</span> — görsel sonradan admin panelden eklenir, post Draft olarak kaydedilir<br>
                                        <strong>vertex</strong> <span class="badge bg-teal">yeni</span> — Vertex AI'dan üretilmiş görseller (tag/prompt adı/ID ile eşleşir, caption+hashtag otomatik gelir)<br>
                                        <strong>ai</strong> — Imagen ile AI görsel üret (Reels desteklenmez)<br>
                                        <strong>galeri</strong> — özel gün galerisinden (tarih eşleşmesi)<br>
                                        <strong>kutuphane</strong> — görsel kütüphanesinden (konu eşleşmesi)<br>
                                        <strong>url</strong> — public URL'den indir<br>
                                        <strong>upload</strong> — ZIP içindeki dosya
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-center"><strong>H</strong></td>
                                    <td><code>gorsel_degeri</code></td>
                                    <td>konu kullanılır</td>
                                    <td>
                                        <strong>vertex:</strong> tag adı, prompt adı, anahtar kelime veya generation ID<br>
                                        <strong>ai:</strong> Imagen prompt (boşsa konu)<br>
                                        <strong>url:</strong> public URL<br>
                                        <strong>upload:</strong> ZIP içindeki dosya adı<br>
                                        <strong>galeri:</strong> görmezden gelinir (tarihle eşleşir)<br>
                                        <strong>panel:</strong> görmezden gelinir
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-center"><strong>I</strong></td>
                                    <td><code>facebook</code></td>
                                    <td><code>evet</code></td>
                                    <td><code>evet</code> | <code>hayir</code> (Story için zorla <code>hayir</code>)</td>
                                </tr>
                                <tr>
                                    <td class="text-center"><strong>J</strong></td>
                                    <td><code>prompt_template</code></td>
                                    <td><span class="text-muted">form'daki seçim</span></td>
                                    <td>
                                        <strong>Per-row override</strong> — <em>opsiyonel</em>: yukarıda formdaki AI Prompt Template seçiminin yerine bu satıra özel template adı yaz.
                                        Sadece <code>gorsel_kaynagi=ai</code> satırlarında dikkate alınır. Hatalı isim yazılırsa form default'a düşer.
                                        <small class="text-muted d-block mt-1">Templates yönetimi: <a href="{{ route('admin.ai-images.index') }}" class="text-teal">/admin/ai-images</a></small>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="alert alert-info small mt-3 mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        <strong>Yeni:</strong> <code>gorsel_kaynagi=panel</code> — sadece <code>tarih</code> doldurup geri kalanını boş bırakırsan,
                        post <strong>Draft</strong> olarak kaydedilir. Sonra
                        <a href="{{ route('admin.instagram-posts.index') }}?status=draft">Taslaklar</a> listesinden açıp görsel + caption ekleyip planlayabilirsin.
                    </div>
                </div>
            </div>

            {{-- Sınırlar --}}
            <div class="card-dark mt-4">
                <div class="card-body-custom">
                    <h6 class="mb-3"><i class="bi bi-info-circle me-1"></i> Sınırlar</h6>
                    <ul class="small mb-0">
                        <li>Tek seferde max <strong>{{ $maxRows }} satır</strong> (AI maliyetini sınırlar — ~$2 üst limit)</li>
                        <li>Excel max <strong>5 MB</strong>, ZIP max <strong>100 MB</strong></li>
                        <li>Reels için AI görsel desteklenmiyor — <strong>url</strong>, <strong>upload</strong> veya <strong>panel</strong> kullan</li>
                        <li>Story Facebook'a paylaşılmaz (Page Story API yok)</li>
                        <li>Geçmiş tarihli satırlar reddedilir</li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- SAĞ: Son toplu planlamalar --}}
        <div class="col-lg-5">
            <div class="card-dark">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-orange"><i class="bi bi-clock-history"></i></div>
                        <div>
                            <h6 class="mb-0">Son Toplu Planlamalar</h6>
                            <small class="text-muted">Son 5 import</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    @forelse($recentImports as $imp)
                    <a href="{{ route('admin.instagram-posts.bulk.show', $imp->id) }}" class="bulk-recent-item">
                        <div class="bulk-recent-meta">
                            <strong>#{{ $imp->id }}</strong>
                            <span class="bulk-status bulk-status-{{ $imp->status }}">
                                {{ match($imp->status) {
                                    \App\Models\BulkImport::STATUS_PENDING        => 'Bekliyor',
                                    \App\Models\BulkImport::STATUS_PROCESSING     => 'İşleniyor',
                                    \App\Models\BulkImport::STATUS_COMPLETED      => 'Tamamlandı',
                                    \App\Models\BulkImport::STATUS_PARTIAL_FAILED => 'Kısmen Başarılı',
                                    \App\Models\BulkImport::STATUS_FAILED         => 'Başarısız',
                                    default                                       => $imp->status,
                                } }}
                            </span>
                        </div>
                        <div class="bulk-recent-info">
                            <small class="text-muted">
                                {{ $imp->created_at->format('d.m.Y H:i') }} —
                                {{ $imp->success_count }}/{{ $imp->total_rows }} başarılı
                                @if($imp->fail_count > 0)
                                    , <span class="text-danger">{{ $imp->fail_count }} hata</span>
                                @endif
                            </small>
                        </div>
                    </a>
                    @empty
                    <p class="text-muted small mb-0 text-center">Henüz toplu plan yapılmadı.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════════
         AI Prompt Modal — Excel sütun yapısını AI'a "anlat" ve hazır içerik
         üret. Kullanıcı kopyalar → ChatGPT/Gemini/Claude'a verir → AI TSV
         formatında Excel'e yapıştırılabilir tablo döner.
         ════════════════════════════════════════════════════════════════ --}}
    {{-- ════════════════════════════════════════════════════════════════
         Excel Önizleme Modal — yükleme ÖNCESİ validation kontrolü
         POST /admin/instagram-posts/bulk/preview ile parse + ilk 10 satır
         ════════════════════════════════════════════════════════════════ --}}
    <div class="modal fade" id="bulkPreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content bulk-preview-modal">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-eye-fill text-info me-2"></i>
                        Excel Önizleme
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>

                <div class="modal-body">
                    {{-- Loading --}}
                    <div id="bulkPreviewLoading" class="text-center py-5">
                        <span class="spinner-border text-info" role="status"></span>
                        <div class="mt-2 small text-muted">Excel okunuyor, satırlar doğrulanıyor…</div>
                    </div>

                    {{-- Hata (parse fail veya HTTP hata) --}}
                    <div id="bulkPreviewParseError" class="alert alert-danger d-none">
                        <i class="bi bi-exclamation-octagon-fill me-1"></i>
                        <span id="bulkPreviewParseErrorMsg"></span>
                    </div>

                    {{-- Content --}}
                    <div id="bulkPreviewContent" class="d-none">
                        {{-- Stats kartları --}}
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <div class="bulk-preview-stat bulk-preview-stat--total">
                                    <span class="bulk-preview-stat__label">Toplam Satır</span>
                                    <span class="bulk-preview-stat__value" id="bulkPreviewTotal">0</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="bulk-preview-stat bulk-preview-stat--valid">
                                    <span class="bulk-preview-stat__label"><i class="bi bi-check-circle-fill me-1"></i> Geçerli</span>
                                    <span class="bulk-preview-stat__value" id="bulkPreviewValid">0</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="bulk-preview-stat bulk-preview-stat--error">
                                    <span class="bulk-preview-stat__label"><i class="bi bi-x-circle-fill me-1"></i> Hatalı</span>
                                    <span class="bulk-preview-stat__value" id="bulkPreviewError">0</span>
                                </div>
                            </div>
                        </div>

                        {{-- Validation hataları (varsa) --}}
                        <div id="bulkPreviewErrorsBlock" class="alert alert-warning d-none mb-3">
                            <strong><i class="bi bi-exclamation-triangle-fill me-1"></i> Validation Hataları:</strong>
                            <ul class="mb-0 mt-1 ps-4 small" id="bulkPreviewErrorsList"></ul>
                            <small class="d-block mt-2 text-muted">
                                Bu satırlar yüklemede atlanır. Excel'de düzeltip tekrar önizleyebilirsin.
                            </small>
                        </div>

                        {{-- Hiç geçerli satır yoksa banner --}}
                        <div id="bulkPreviewNoValid" class="alert alert-danger d-none">
                            <i class="bi bi-x-octagon-fill me-1"></i>
                            <strong>Hiç geçerli satır bulunamadı!</strong>
                            Excel'i düzeltmen gerekiyor — yüklemenin yapılması şu an mümkün değil.
                        </div>

                        {{-- İlk 10 valid satır tablosu --}}
                        <div id="bulkPreviewTableWrap" class="d-none">
                            <h6 class="text-clr-secondary mb-2">
                                <i class="bi bi-table me-1"></i>
                                İlk Geçerli Satırlar (max 10)
                            </h6>
                            <div class="table-responsive bulk-preview-table-wrap">
                                <table class="cl-table bulk-preview-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Tarih</th>
                                            <th>Saat</th>
                                            <th>Tip</th>
                                            <th>Konu</th>
                                            <th>Caption</th>
                                            <th>Hashtags</th>
                                            <th>Görsel</th>
                                            <th>Değer</th>
                                            <th>FB</th>
                                            <th>Template</th>
                                        </tr>
                                    </thead>
                                    <tbody id="bulkPreviewRows"></tbody>
                                </table>
                            </div>
                            <small class="text-muted d-block mt-2">
                                💡 <strong>Önizleme tamam görünüyorsa</strong> modal'ı kapat, "Yükle ve İşlemeye Başla" butonuna bas.
                            </small>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-glass" data-bs-dismiss="modal">Kapat</button>
                    <button type="button" class="btn-teal d-none" id="bulkPreviewProceedBtn" data-bs-dismiss="modal">
                        <i class="bi bi-rocket-takeoff me-1"></i> Önizleme Tamam, Yüklemeye Devam
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="bulkAiPromptModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content bulk-ai-modal">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-stars text-warning me-2"></i>
                        AI'ya Verilecek Hazır Prompt
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-info small mb-3">
                        <strong><i class="bi bi-info-circle me-1"></i> Nasıl kullanılır?</strong>
                        <ol class="mb-0 mt-1 ps-4">
                            <li>Aşağıdaki prompt'u <strong>Kopyala</strong> butonuyla al</li>
                            <li><a href="https://gemini.google.com" target="_blank" rel="noopener" class="text-teal">Gemini</a>,
                                <a href="https://chat.openai.com" target="_blank" rel="noopener" class="text-teal">ChatGPT</a> veya
                                <a href="https://claude.ai" target="_blank" rel="noopener" class="text-teal">Claude</a>'a yapıştır</li>
                            <li>Prompt'un sonundaki <code>[SAYI]</code> yerine kaç satır istediğini yaz (örn. <code>30</code>)</li>
                            <li>AI'ın ürettiği <strong>TSV tabloyu</strong> kopyala → Excel'e yapıştır → bu sayfadan yükle</li>
                        </ol>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong class="text-clr-secondary">
                            <i class="bi bi-file-earmark-text me-1"></i>
                            AI Prompt İçeriği
                        </strong>
                        <button type="button" class="btn-teal btn-sm" id="bulkAiPromptCopyBtn">
                            <i class="bi bi-clipboard me-1"></i>
                            <span data-default>Kopyala</span>
                            <span data-success class="d-none"><i class="bi bi-check-lg me-1"></i>Kopyalandı!</span>
                        </button>
                    </div>

                    @php
                        $today = now()->format('Y-m-d');
                        $tomorrow = now()->addDay()->format('Y-m-d');
                        $dayAfter = now()->addDays(2)->format('Y-m-d');
                        // Defansif: $previewSettings controller'da set edilmiyorsa veya
                        // site_name boşsa, Setting'ten doğrudan oku, son fallback olarak sabit
                        $brand = trim((string) (
                            (isset($previewSettings) && is_array($previewSettings) && ! empty($previewSettings['site_name']))
                                ? $previewSettings['site_name']
                                : \App\Models\Setting::getValue('site_name', 'Orhan Babanın Çiftliği')
                        ));
                        if ($brand === '') {
                            $brand = 'Orhan Babanın Çiftliği';
                        }
                        // Aktif AI prompt template'lerinin adlarını ekleyelim — AI hangi template'lerin
                        // kullanılabilir olduğunu bilsin (J kolonu için)
                        $templateNames = isset($promptTemplates) && method_exists($promptTemplates, 'pluck')
                            ? $promptTemplates->pluck('name')->take(8)->all()
                            : [];
                        $templateLine = empty($templateNames) ? '(henüz kayıtlı template yok)' : implode(', ', $templateNames);

                        // Medya tipi seçimine göre instruction varyantları (JS'de switch edilir).
                        // Her instruction prompt'taki [MEDIA_INSTRUCTION] yerine geçer.
                        $mediaInstructions = [
                            'mixed' => "- %60 image (feed) + %20 reels + %20 story dağılımı\n- gorsel_kaynagi çoğunlukla 'panel' (kullanıcı sonra ekleyecek görseli)\n- Bazı satırlarda 'ai' kullanılabilir (özel ürün tanıtımı için)",
                            'feed'  => "- TÜM satırlar tip=image (Feed Post) — reels veya story KULLANMA\n- gorsel_kaynagi çoğunlukla 'panel'; özel satırlarda 'ai' (Imagen prompt) olabilir\n- Carousel post için aynı satırda birden fazla görsel desteklenmez (1 satır = 1 post)\n- facebook=evet (Feed Facebook'a cross-post desteklenir)",
                            'reels' => "- TÜM satırlar tip=reels (kısa video) — image veya story KULLANMA\n- gorsel_kaynagi=panel (kullanıcı video yükleyecek) veya url/upload\n- ÖNEMLİ: Reels için gorsel_kaynagi=ai DESTEKLENMEZ (Imagen video üretmiyor)\n- Reels için konu sahası, perde arkası, üretim süreci, tarif videoları ideal\n- facebook=evet desteklenir (Reels Facebook'a da paylaşılır)",
                            'story' => "- TÜM satırlar tip=story (24 saat sonra silinir) — image/reels KULLANMA\n- gorsel_kaynagi=panel veya ai/url/upload (hepsi destekler)\n- ÖNEMLİ: facebook OTOMATIK 'hayir' olur — Story Facebook'ta paylaşılmaz\n- Story 24 saat sonra Meta tarafından silinir, anlık paylaşım için ideal\n- Hızlı duyurular, kampanya, soru-anket, ürün tanıtımı için kullan",
                        ];

                        // Tonlama seçimleri — caption'ın yazım stilini AI'a anlat
                        $toneInstructions = [
                            'samimi'       => "- Tonlama: Samimi, sıcak, ev hissi (komşu konuşması gibi)\n- 'Bizim çiftliğimizden', 'soframıza buyrun' gibi içten ifadeler\n- Emoji yumuşak (🌾 🥛 🧀 ☕)",
                            'profesyonel'  => "- Tonlama: Profesyonel, kurumsal, vurucu\n- Net ürün özellikleri, 'kalite', 'hijyen', 'sertifikalı' vurgusu\n- Emoji minimal, sadece anahtar yerlerde (✓ ⭐ 📦)",
                            'eglenceli'    => "- Tonlama: Eğlenceli, canlı, esprili\n- Hafif espriler, soru-cevap, oyun çağrıları ('Hangi peyniri seversin?')\n- Emoji bol ve canlı (😋 🤤 🎉 🥳)",
                            'nostaljik'    => "- Tonlama: Nostaljik, geleneksel, köy hissi\n- 'Eski usul', 'dedem zamanından', 'unutulmaz lezzet' anlatımı\n- Emoji sade ve klasik (🌻 🌾 🏡)",
                        ];

                        // Hızlı şablon preset'leri — tek tıkla {satır sayısı, medya, tonlama, ekstra talimat}
                        // JS bu preset'i seçince UI'ı + prompt'u günceller
                        $presets = [
                            'monthly' => [
                                'label'    => 'Aylık Plan',
                                'icon'     => 'bi-calendar-month',
                                'desc'     => '30 gün, karışık içerik',
                                'count'    => 30,
                                'media'    => 'mixed',
                                'tone'     => 'samimi',
                                'extra'    => "- 30 günlük dengeli takvim — her ürünü en az 2-3 kez kullan\n- Hafta içi feed + Cumartesi reels + Pazar story dağılımı tercih et\n- Mevsimsel ürünleri (yaz/kış) öne çıkar",
                            ],
                            'campaign' => [
                                'label'    => 'Bayram Kampanyası',
                                'icon'     => 'bi-gift',
                                'desc'     => '7 gün, kampanya odaklı',
                                'count'    => 7,
                                'media'    => 'mixed',
                                'tone'     => 'eglenceli',
                                'extra'    => "- 7 günlük yoğun kampanya — bayram öncesi sipariş daveti\n- Her satırda kampanya/indirim/hediye paketi vurgusu\n- 'Bayrama özel sipariş', 'Sevdiklerine bayramlık' gibi CTA'lar\n- Story ağırlıklı (anlık duyuru için), 1-2 reels (üretim sahnesi)",
                            ],
                            'launch' => [
                                'label'    => 'Yeni Ürün Lansmanı',
                                'icon'     => 'bi-rocket-takeoff',
                                'desc'     => '10 gün, ürün hikayesi',
                                'count'    => 10,
                                'media'    => 'reels',
                                'tone'     => 'profesyonel',
                                'extra'    => "- Tek bir ürünün 10 günlük lansman takvimi\n- Reels ağırlıklı: üretim süreci, hammadde, tat testi, paketleme, kargo\n- Konular sırayla anlatılsın: tanıtım → fayda → tarif → müşteri yorumu → indirim\n- 'YENİ', 'İlk kez', 'Sınırlı stok' vurguları",
                            ],
                            'seasonal' => [
                                'label'    => 'Mevsimsel İçerik',
                                'icon'     => 'bi-tree',
                                'desc'     => '14 gün, mevsim teması',
                                'count'    => 14,
                                'media'    => 'mixed',
                                'tone'     => 'nostaljik',
                                'extra'    => "- 14 günlük mevsimsel takvim (içinde bulunduğumuz mevsime uygun)\n- İlkbahar: süt-yoğurt-otlar; Yaz: ayran-cacık-salata; Sonbahar: reçel-pekmez; Kış: tarhana-kavurma-helva\n- Mevsime özel tarif önerileri\n- Doğa fotoğrafları, mevsim renkleri (panel modunda kullanıcı seçecek)",
                            ],
                            'specialdays' => [
                                'label'    => 'Yıllık Özel Günler',
                                'icon'     => 'bi-calendar-heart',
                                'desc'     => 'Yılbaşı, bayramlar, kandiller (Story)',
                                'count'    => count(($specialDaysByYear ?? [])[$specialDaysYear ?? 0] ?? []),
                                'media'    => 'story',
                                'tone'     => 'nostaljik',
                                'extra'    => "- ZORUNLU TAKVİM: Aşağıdaki tarih → özel gün eşlemesini aynen kullan, sıra/tarih DEĞIŞTIRMEK YASAK\n- Her satırda A sütunu (tarih) tam o özel günün tarihini içerecek\n- B sütunu (saat) = '09:00' (sabah hikayesi için ideal)\n- D sütunu (konu) = '{Özel Gün Adı} – Çiftlikten' formatında\n- E sütunu (caption) = AI tarafından özel günün TEMA'sına göre üretilecek (kısa, 1-2 cümle, story için ideal)\n- F sütunu (hashtags) = özel günün adı + 'çiftlik,doğal' gibi alakalı 3-5 etiket\n- G sütunu (gorsel_kaynagi) = 'galeri' (galeriden least-used görsel otomatik seçilir, AI maliyeti $0)\n- I sütunu (facebook) = 'hayir' (Story zaten Facebook'a paylaşılmaz)\n- J sütunu (prompt_template) = boş (görsel galeriden geleceği için template'e gerek yok)\n- ÖNEMLİ: Saygı isteyen günlerde (Çanakkale, 10 Kasım, Atatürk anma) tonlama nostaljiği aşıp 'sade ve onurlu' olsun, emoji kullanma\n- NOT: Galerinde o güne ait görsel yoksa import o satırda fail eder — önce /admin/ozel-gunler/{id}/galeri sayfasından görsel ekle",
                            ],
                        ];

                        // Base template — placeholder'lar JS tarafında runtime replace edilir
                        // [TODAY], [TOMORROW], [DAYAFTER] tarih bağımlı, [SAYI] satır sayısı,
                        // [MEDIA_INSTRUCTION], [TONE_INSTRUCTION], [PRESET_EXTRA] seçim bağımlı
                        $basePromptTemplate = "ROL: Sen bir Instagram içerik takvim uzmanısın. \"{$brand}\" adlı doğal/organik gıda firması (Çorum'dan köy ürünleri satıyor) için Instagram içerik takvimi hazırlıyorsun.

GÖREV: Aşağıdaki ŞEMAYA harfiyen uygun, **TSV (tab-separated values)** formatında bir tablo üret. İlk satır header, sonraki satırlar veri. Sadece tabloyu döndür, başka açıklama yapma, kod bloğu da kullanma.

═══ ŞEMA (10 sütun, sıralı — A → J) ═══

A. tarih (ZORUNLU) — YYYY-MM-DD formatı (örn. [TODAY])
B. saat — HH:MM formatı, varsayılan 19:00
C. tip — image | reels | story (varsayılan image)
D. konu — AI caption ve görsel için bağlam (panel modu hariç opsiyonel)
E. caption — Manuel yazı; boşsa konu'dan AI üretir (panel modunda boş kalır)
F. hashtags — Virgülle ayrılmış (örn. doğal,çiftlik,köy); boşsa AI üretir
G. gorsel_kaynagi — panel (varsayılan) | ai | url | upload
H. gorsel_degeri — Source-bağımlı:
     • ai     → Imagen prompt metni (boşsa konu kullanılır)
     • url    → public görsel/video URL'i
     • upload → ZIP içindeki dosya adı (örn. urun-1.jpg)
     • panel  → boş bırak (görmezden gelinir)
I. facebook — evet | hayir (varsayılan evet; Story için zorla 'hayir' olur)
J. prompt_template — Mevcut AI prompt template adı (per-row override).
     Boş = formdaki default template kullanılır.
     Mevcut template'ler: {$templateLine}

═══ KURALLAR (uy) ═══

1. Geçmiş tarih KABUL EDİLMEZ → tüm tarihler [TODAY] tarihinden başlasın
2. Reels için gorsel_kaynagi=ai DESTEKLENMEZ → url, upload veya panel kullan
3. Story için facebook OTOMATIK 'hayir' (yazsan da değişir)
4. Max {$maxRows} satır
5. TSV: hücreler arası TAB karakteri, satırlar arası NEW LINE
6. Boş hücreyi yine de TAB ile geç (sütun sayısı her satırda 10 olmalı)
7. Markdown, kod bloğu, açıklama, yorum YASAK — sadece tablo

═══ İÇERİK STRATEJISI ═══

- Konular çeşitli olsun: süt, peynir, tereyağı, yoğurt, bal, yumurta, çökelek
- Çiftlik hikayeleri: sabah sağımı, kahvaltı, yayık tereyağı yapımı, hasat
- Türk mutfağı: tarif paylaşımları, mevsimsel öneriler
- Kalite vurgusu: doğal, organik, geleneksel, ev yapımı, katkısız
- Çorum'dan kargo bilgisi (yer-yer)
[MEDIA_INSTRUCTION]
[TONE_INSTRUCTION][PRESET_EXTRA][TOPIC_LIST]

═══ ÖRNEK ÇIKTI (ilk 3 satır — sen daha fazla üret) ═══

tarih\tsaat\ttip\tkonu\tcaption\thashtags\tgorsel_kaynagi\tgorsel_degeri\tfacebook\tprompt_template
[TODAY]\t09:00\timage\tSabah sağımı\tSabah çiftliğimizden günaydın!\tçiftlik,köy,doğal\tpanel\t\tevet\t
[TOMORROW]\t14:00\treels\tYayık tereyağı yapımı\t\tgeleneksel,tereyağı\tpanel\t\tevet\t
[DAYAFTER]\t19:00\tstory\tAkşam kahvaltısı\tAkşam soframıza ne dersin?\t\tpanel\t\thayir\t

═══ GÖREVİN ═══

Bana [SAYI] satırlık bir Instagram içerik takvimi üret.
- Tarih [TODAY] tarihinden başlasın, her gün 1 satır
- Yukarıdaki şemaya, kurallara ve içerik stratejisine UY
- ÇIKTI: SADECE TSV tablosu (header dahil), başka hiçbir şey yazma";

                        // İlk yüklemede default değerlerle render et — JS sonradan günceller
                        $initialPrompt = strtr($basePromptTemplate, [
                            '[TODAY]'             => $today,
                            '[TOMORROW]'          => $tomorrow,
                            '[DAYAFTER]'          => $dayAfter,
                            '[SAYI]'              => '30',
                            '[MEDIA_INSTRUCTION]' => $mediaInstructions['mixed'],
                            '[TONE_INSTRUCTION]'  => $toneInstructions['samimi'],
                            '[PRESET_EXTRA]'      => '',
                            '[TOPIC_LIST]'        => '',
                        ]);
                        // TSV escape: \t ve \n literal string olarak kalsın
                        $initialPrompt = strtr($initialPrompt, ['\t' => "\t", '\n' => "\n"]);

                        // JS için tüm data'yı tek değişkene topla — @json($var) inline kullanır
                        $bulkAiPromptDataPayload = [
                            'template'          => $basePromptTemplate,
                            'mediaInstructions' => $mediaInstructions,
                            'toneInstructions'  => $toneInstructions,
                            'presets'           => $presets,
                            'today'             => $today,
                            // Yıllık özel günler — "specialdays" preset'i için
                            'specialDays'       => [
                                'currentYear' => $specialDaysYear ?? (int) now()->format('Y'),
                                'years'       => array_values($specialDaysYears ?? []),
                                'byYear'      => $specialDaysByYear ?? [],
                            ],
                        ];
                    @endphp

                    {{-- ════════ Hızlı Şablon Preset'leri ════════ --}}
                    <div class="mb-3">
                        <strong class="text-clr-secondary d-block mb-2">
                            <i class="bi bi-stars me-1 text-warning"></i> Hızlı Şablon
                            <small class="text-muted ms-1">(tek tıkla tüm ayarları doldurur)</small>
                        </strong>
                        <div class="row g-2 bulk-ai-preset-grid">
                            @foreach($presets as $key => $p)
                                <div class="col-md-6 col-lg-4 col-xl">
                                    <button type="button" class="bulk-ai-preset-card w-100" data-preset="{{ $key }}">
                                        <i class="bi {{ $p['icon'] }}"></i>
                                        <strong>{{ $p['label'] }}</strong>
                                        <small class="text-muted d-block">{{ $p['desc'] }}</small>
                                    </button>
                                </div>
                            @endforeach
                        </div>

                        {{-- Özel günler için yıl seçici (sadece "specialdays" aktifken görünür) --}}
                        <div class="bulk-ai-specialdays-controls" id="bulkAiSpecialDaysControls" hidden>
                            <div class="bulk-ai-specialdays-info">
                                <i class="bi bi-info-circle-fill me-1 text-warning"></i>
                                <span id="bulkAiSpecialDaysSummary"></span>
                            </div>
                            <label class="bulk-ai-specialdays-year-label">
                                <strong>Yıl:</strong>
                                <select id="bulkAiSpecialDaysYear" class="form-select form-select-sm bulk-ai-specialdays-year">
                                    @foreach(($specialDaysYears ?? []) as $y)
                                        <option value="{{ $y }}" @selected($y === ($specialDaysYear ?? null))>{{ $y }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    </div>

                    {{-- ════════ Satır Sayısı + Tarih Başlangıcı ════════ --}}
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="text-clr-secondary d-block mb-2">
                                <strong><i class="bi bi-list-ol me-1"></i> Kaç satır?</strong>
                                <small class="text-muted">(max {{ $maxRows }})</small>
                            </label>
                            <div class="d-flex gap-2 align-items-center">
                                <input type="number" id="bulkAiCount" class="form-control bulk-ai-count-input"
                                       value="30" min="1" max="{{ $maxRows }}" step="1">
                                <div class="bulk-ai-count-quick">
                                    <button type="button" class="btn-glass btn-glass-sm" data-count="7">7</button>
                                    <button type="button" class="btn-glass btn-glass-sm" data-count="14">14</button>
                                    <button type="button" class="btn-glass btn-glass-sm" data-count="30">30</button>
                                    <button type="button" class="btn-glass btn-glass-sm" data-count="50">50</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-clr-secondary d-block mb-2">
                                <strong><i class="bi bi-calendar-event me-1"></i> Hangi tarihten başlasın?</strong>
                            </label>
                            <div class="d-flex gap-2 align-items-center">
                                <input type="date" id="bulkAiStartDate" class="form-control bulk-ai-date-input"
                                       value="{{ $today }}" min="{{ $today }}">
                                <div class="bulk-ai-date-quick">
                                    <button type="button" class="btn-glass btn-glass-sm" data-date-offset="0">Bugün</button>
                                    <button type="button" class="btn-glass btn-glass-sm" data-date-offset="1">Yarın</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ════════ Medya Tipi Seçimi ════════ --}}
                    <div class="mb-3">
                        <strong class="text-clr-secondary d-block mb-2">
                            <i class="bi bi-collection me-1"></i> Hangi tip içerik üretilsin?
                        </strong>
                        <div class="d-flex flex-wrap gap-2 bulk-ai-media-group" role="group" aria-label="İçerik tipi">
                            <label class="bulk-ai-media-opt is-active" data-media="mixed">
                                <input type="radio" name="bulkAiMedia" value="mixed" checked class="visually-hidden">
                                <i class="bi bi-collection-fill me-1"></i>
                                <strong>Karışık</strong>
                                <small class="d-block text-muted">%60 feed + %20 reels + %20 story</small>
                            </label>
                            <label class="bulk-ai-media-opt" data-media="feed">
                                <input type="radio" name="bulkAiMedia" value="feed" class="visually-hidden">
                                <i class="bi bi-image-fill me-1"></i>
                                <strong>Sadece Feed</strong>
                                <small class="d-block text-muted">image post (kalıcı)</small>
                            </label>
                            <label class="bulk-ai-media-opt" data-media="reels">
                                <input type="radio" name="bulkAiMedia" value="reels" class="visually-hidden">
                                <i class="bi bi-camera-reels-fill me-1"></i>
                                <strong>Sadece Reels</strong>
                                <small class="d-block text-muted">kısa video (kalıcı)</small>
                            </label>
                            <label class="bulk-ai-media-opt" data-media="story">
                                <input type="radio" name="bulkAiMedia" value="story" class="visually-hidden">
                                <i class="bi bi-circle-fill me-1"></i>
                                <strong>Sadece Story</strong>
                                <small class="d-block text-muted">24 saat sonra silinir</small>
                            </label>
                        </div>
                    </div>

                    {{-- ════════ Tonlama Seçici ════════ --}}
                    <div class="mb-3">
                        <strong class="text-clr-secondary d-block mb-2">
                            <i class="bi bi-chat-quote me-1"></i> Caption tonlaması nasıl olsun?
                        </strong>
                        <div class="d-flex flex-wrap gap-2 bulk-ai-tone-group" role="group" aria-label="Tonlama">
                            <label class="bulk-ai-tone-opt is-active" data-tone="samimi">
                                <input type="radio" name="bulkAiTone" value="samimi" checked class="visually-hidden">
                                <i class="bi bi-house-heart-fill me-1"></i>
                                <strong>Samimi</strong>
                                <small class="d-block text-muted">ev hissi, sıcak</small>
                            </label>
                            <label class="bulk-ai-tone-opt" data-tone="profesyonel">
                                <input type="radio" name="bulkAiTone" value="profesyonel" class="visually-hidden">
                                <i class="bi bi-briefcase-fill me-1"></i>
                                <strong>Profesyonel</strong>
                                <small class="d-block text-muted">kurumsal, vurucu</small>
                            </label>
                            <label class="bulk-ai-tone-opt" data-tone="eglenceli">
                                <input type="radio" name="bulkAiTone" value="eglenceli" class="visually-hidden">
                                <i class="bi bi-emoji-laughing-fill me-1"></i>
                                <strong>Eğlenceli</strong>
                                <small class="d-block text-muted">canlı, esprili</small>
                            </label>
                            <label class="bulk-ai-tone-opt" data-tone="nostaljik">
                                <input type="radio" name="bulkAiTone" value="nostaljik" class="visually-hidden">
                                <i class="bi bi-tree-fill me-1"></i>
                                <strong>Nostaljik</strong>
                                <small class="d-block text-muted">geleneksel, köy hissi</small>
                            </label>
                        </div>
                    </div>

                    {{-- ════════ Konu Havuzu (AI Önerileri) ════════ --}}
                    <div class="mb-3 bulk-ai-topic-pool">
                        <strong class="text-clr-secondary d-block mb-2">
                            <i class="bi bi-lightbulb-fill me-1 text-warning"></i> Konu Havuzu (AI Önerileri)
                            <small class="text-muted ms-1">— firmana özel konu listesi al, beğendiklerini seç</small>
                        </strong>

                        <div class="bulk-ai-topic-controls">
                            <input type="text" id="bulkAiTopicTheme" class="form-control form-control-sm bulk-ai-topic-theme"
                                   placeholder="Tema (opsiyonel) — örn: Bayram, Kış, Ramazan..."
                                   maxlength="100" autocomplete="off">
                            <button type="button" class="btn-teal btn-sm" id="bulkAiTopicSuggestBtn">
                                <i class="bi bi-stars me-1"></i>
                                <span data-default>30 Konu Öner</span>
                                <span data-loading class="d-none">
                                    <i class="bi bi-arrow-repeat me-1"></i> Üretiliyor...
                                </span>
                            </button>
                            <div class="bulk-ai-topic-actions">
                                <button type="button" class="btn-glass btn-glass-sm" id="bulkAiTopicSelectAllBtn" hidden>
                                    <i class="bi bi-check-all me-1"></i> Tümünü Seç
                                </button>
                                <button type="button" class="btn-glass btn-glass-sm" id="bulkAiTopicClearBtn" hidden>
                                    <i class="bi bi-x-lg me-1"></i> Temizle
                                </button>
                            </div>
                            <span class="bulk-ai-topic-counter" id="bulkAiTopicCounter" hidden>
                                <strong id="bulkAiTopicSelectedCount">0</strong> / <span id="bulkAiTopicTotal">0</span> seçili
                            </span>
                        </div>

                        <div class="bulk-ai-topic-status" id="bulkAiTopicStatus" hidden></div>
                        <div class="bulk-ai-topic-grid" id="bulkAiTopicList"></div>

                        <small class="text-muted mt-2 d-block">
                            <i class="bi bi-info-circle me-1"></i>
                            Seçtiğin konular AI prompt'una otomatik eklenir → AI <strong>sadece</strong> bu konularda caption üretir
                            ve <code>Kaç satır?</code> alanı seçimine göre senkronlanır.
                        </small>
                    </div>

                    <textarea id="bulkAiPromptText" class="form-control bulk-ai-prompt-text" readonly rows="22">{{ $initialPrompt }}</textarea>

                    {{-- JS için tüm template + instruction varyantları + preset'ler --}}
                    <script type="application/json" id="bulkAiPromptData">@json($bulkAiPromptDataPayload)</script>

                    {{-- ════════ Excel'e Yapıştırma Rehberi (3 adım) ════════ --}}
                    <div class="bulk-ai-paste-guide mt-4">
                        <strong class="text-clr-secondary d-block mb-3">
                            <i class="bi bi-question-circle me-1"></i>
                            AI çıktısını Excel'e nasıl yapıştırırım?
                        </strong>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="bulk-ai-step-card">
                                    <div class="bulk-ai-step-num">1</div>
                                    <i class="bi bi-clipboard-check"></i>
                                    <strong>AI çıktısını seç + kopyala</strong>
                                    <small class="text-muted d-block">
                                        Gemini/ChatGPT'nin yanıt kutusundaki TSV tablosunu seç (Ctrl+A) ve kopyala (Ctrl+C).
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="bulk-ai-step-card">
                                    <div class="bulk-ai-step-num">2</div>
                                    <i class="bi bi-file-earmark-spreadsheet"></i>
                                    <strong>Excel'i aç → A1'e yapıştır</strong>
                                    <small class="text-muted d-block">
                                        Yeni boş Excel dosyası aç, <strong>A1</strong> hücresini seç, Ctrl+V yapıştır. TSV otomatik sütunlara ayrılır.
                                        <br><em>Ayrılmazsa:</em> Veri → Sütunlara Böl → "Tab" seç.
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="bulk-ai-step-card">
                                    <div class="bulk-ai-step-num">3</div>
                                    <i class="bi bi-cloud-upload"></i>
                                    <strong>.xlsx olarak kaydet → yükle</strong>
                                    <small class="text-muted d-block">
                                        Dosya → Farklı Kaydet → <strong>.xlsx</strong> formatı. Sonra bu sayfadaki "Excel Dosyası" alanına yükle.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning small mt-3 mb-0">
                        <strong><i class="bi bi-lightbulb me-1"></i> İpucu:</strong>
                        AI'ın çıktısını <strong>Gemini → Excel'e Aktar</strong> butonuyla veya direkt seçip kopyalayıp Excel'e yapıştırabilirsin.
                        Excel'de TSV otomatik sütunlara ayrılır. Sonra <strong>"Excel Şablon İndir"</strong> butonundaki dosya yapısıyla aynı olduğu için doğrudan yükleyebilirsin.
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-glass" data-bs-dismiss="modal">Kapat</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════════
         KÜTÜPHANEDEN PLAN OLUŞTUR Modal — Faz 4 Görsel-İlk Akış
         AI maliyeti $0 — kütüphaneden least-used görseller seçilir
         ═══════════════════════════════════════════════════════════════ --}}
    <div class="modal fade" id="bulkLibraryPlanModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content bulk-ai-modal">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-images text-warning me-2"></i>
                        Kütüphaneden Otomatik Plan Oluştur
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info small mb-3">
                        <strong><i class="bi bi-info-circle me-1"></i> Nasıl çalışır?</strong>
                        Tarih aralığı + tip dağılımı seçince kütüphaneden least-used görseller otomatik seçilir,
                        her güne dengeli dağıtılır. <strong>AI görsel maliyeti $0</strong> — sadece minimal caption AI maliyeti kalır.
                        <br><small class="text-muted mt-1 d-block">Önce <a href="{{ route('admin.image-library.index') }}" target="_blank" class="text-teal">Görsel Kütüphanesi</a>'ne yeterli sayıda etiketli görsel yüklediğinden emin ol.</small>
                    </div>

                    <form id="bulkPlanForm">
                        @csrf
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Başlangıç tarihi</label>
                                <input type="date" name="start_date" class="form-control form-control-theme"
                                       value="{{ now()->toDateString() }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Bitiş tarihi</label>
                                <input type="date" name="end_date" class="form-control form-control-theme"
                                       value="{{ now()->addDays(29)->toDateString() }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Günde post sayısı</label>
                                <select name="post_per_day" class="form-select form-control-theme">
                                    <option value="1" selected>1 post / gün</option>
                                    <option value="2">2 post / gün</option>
                                    <option value="3">3 post / gün</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tip Dağılımı (toplam 100%)</label>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="small text-muted">📷 Feed (%)</label>
                                    <input type="number" name="feed_pct" id="bulkPlanFeedPct" class="form-control form-control-theme bulk-pct-input"
                                           min="0" max="100" step="5" value="60">
                                </div>
                                <div class="col-md-4">
                                    <label class="small text-muted">📍 Story (%)</label>
                                    <input type="number" name="story_pct" id="bulkPlanStoryPct" class="form-control form-control-theme bulk-pct-input"
                                           min="0" max="100" step="5" value="25">
                                </div>
                                <div class="col-md-4">
                                    <label class="small text-muted">🎬 Reels (%)</label>
                                    <input type="number" name="reels_pct" id="bulkPlanReelsPct" class="form-control form-control-theme bulk-pct-input"
                                           min="0" max="100" step="5" value="15">
                                </div>
                            </div>
                            <small class="form-text" id="bulkPlanPctSum">Toplam: <strong>100%</strong></small>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Tema (opsiyonel)</label>
                                <select name="theme" class="form-select form-control-theme">
                                    <option value="">— Hepsi —</option>
                                    @foreach(\App\Models\MediaLibraryImage::THEMES as $t)
                                        <option value="{{ $t }}">{{ (new \App\Models\MediaLibraryImage(['theme' => $t]))->themeLabel() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Mood (opsiyonel)</label>
                                <select name="mood" class="form-select form-control-theme">
                                    <option value="">— Hepsi —</option>
                                    @foreach(\App\Models\MediaLibraryImage::MOODS as $m)
                                        <option value="{{ $m }}">{{ ucfirst($m) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Mevsim (opsiyonel)</label>
                                <select name="season" class="form-select form-control-theme">
                                    <option value="">— Hepsi —</option>
                                    @foreach(\App\Models\MediaLibraryImage::SEASONS as $s)
                                        <option value="{{ $s }}">{{ ucfirst($s) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="d-flex gap-2 justify-content-end">
                            <button type="button" class="btn-teal" id="bulkPlanPreviewBtn">
                                <i class="bi bi-eye me-1"></i>
                                <span data-default>Plan Önizle</span>
                                <span data-loading class="d-none"><i class="bi bi-hourglass-split me-1"></i> Hazırlanıyor...</span>
                            </button>
                        </div>
                    </form>

                    {{-- Plan Sonucu --}}
                    <div id="bulkPlanResult" class="mt-3"></div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-glass" data-bs-dismiss="modal">Kapat</button>
                    <button type="button" class="btn-teal" id="bulkPlanExecuteBtn" disabled>
                        <i class="bi bi-rocket-takeoff me-1"></i>
                        <span data-default>Plan'ı Onayla ve İçe Aktar</span>
                        <span data-loading class="d-none"><i class="bi bi-hourglass-split me-1"></i> İşleniyor...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('styles')
<style>
.bulk-ai-modal {
    background: rgba(15, 23, 42, 0.98);
    border: 1px solid rgba(255, 255, 255, 0.08);
    color: #e5e7eb;
}
.bulk-ai-prompt-text {
    background: rgba(0, 0, 0, 0.5);
    color: #d4d4d4;
    border: 1px solid rgba(255, 255, 255, 0.08);
    font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
    font-size: 0.78rem;
    line-height: 1.5;
    white-space: pre;
    overflow-x: auto;
    resize: vertical;
}
.bulk-ai-prompt-text:focus {
    background: rgba(0, 0, 0, 0.6);
    color: #fff;
    border-color: rgba(20, 184, 166, 0.5);
    box-shadow: 0 0 0 0.2rem rgba(20, 184, 166, 0.15);
}

/* Medya tipi seçici — radio button'u tıklanabilir card */
.bulk-ai-media-group {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.bulk-ai-media-opt {
    flex: 1 1 calc(25% - 8px);
    min-width: 160px;
    padding: 10px 12px;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 8px;
    color: rgba(255, 255, 255, 0.85);
    cursor: pointer;
    transition: background 0.15s ease, border-color 0.15s ease, transform 0.1s ease;
    text-align: center;
    margin: 0;
    user-select: none;
}
.bulk-ai-media-opt i {
    font-size: 1.1rem;
}
.bulk-ai-media-opt strong {
    display: block;
    font-size: 0.92rem;
    margin-top: 2px;
}
.bulk-ai-media-opt:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(20, 184, 166, 0.3);
}
.bulk-ai-media-opt.is-active {
    background: rgba(20, 184, 166, 0.15);
    border-color: rgba(20, 184, 166, 0.6);
    color: #fff;
    box-shadow: 0 0 0 1px rgba(20, 184, 166, 0.3);
}
.bulk-ai-media-opt.is-active i { color: #14b8a6; }

/* Tonlama seçici — medya seçici ile aynı pattern */
.bulk-ai-tone-group {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.bulk-ai-tone-opt {
    flex: 1 1 calc(25% - 8px);
    min-width: 140px;
    padding: 10px 12px;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 8px;
    color: rgba(255, 255, 255, 0.85);
    cursor: pointer;
    transition: background 0.15s ease, border-color 0.15s ease;
    text-align: center;
    margin: 0;
    user-select: none;
}
.bulk-ai-tone-opt i { font-size: 1.05rem; }
.bulk-ai-tone-opt strong { display: block; font-size: 0.9rem; margin-top: 2px; }
.bulk-ai-tone-opt:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(168, 85, 247, 0.3);
}
.bulk-ai-tone-opt.is-active {
    background: rgba(168, 85, 247, 0.15);
    border-color: rgba(168, 85, 247, 0.6);
    color: #fff;
    box-shadow: 0 0 0 1px rgba(168, 85, 247, 0.3);
}
.bulk-ai-tone-opt.is-active i { color: #c084fc; }

/* Preset kartları — tek tıkla state set */
.bulk-ai-preset-card {
    display: block;
    width: 100%;
    padding: 12px 10px;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 10px;
    color: rgba(255, 255, 255, 0.85);
    cursor: pointer;
    transition: all 0.15s ease;
    text-align: center;
}
.bulk-ai-preset-card i {
    font-size: 1.5rem;
    color: #f59e0b;
    display: block;
    margin-bottom: 6px;
}
.bulk-ai-preset-card strong {
    display: block;
    font-size: 0.92rem;
    color: #fff;
}
.bulk-ai-preset-card small {
    display: block;
    margin-top: 2px;
    font-size: 0.74rem;
}
.bulk-ai-preset-card:hover {
    background: rgba(245, 158, 11, 0.08);
    border-color: rgba(245, 158, 11, 0.4);
    transform: translateY(-2px);
}
.bulk-ai-preset-card.is-active {
    background: rgba(245, 158, 11, 0.18);
    border-color: rgba(245, 158, 11, 0.7);
    box-shadow: 0 0 0 1px rgba(245, 158, 11, 0.4), 0 4px 12px rgba(245, 158, 11, 0.15);
}

/* Satır sayısı + tarih input + hızlı butonlar */
.bulk-ai-count-input,
.bulk-ai-date-input {
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: #fff;
    max-width: 100px;
}
.bulk-ai-date-input { max-width: 160px; }
.bulk-ai-count-input:focus,
.bulk-ai-date-input:focus {
    background: rgba(0, 0, 0, 0.4);
    border-color: rgba(20, 184, 166, 0.5);
    color: #fff;
    box-shadow: 0 0 0 0.2rem rgba(20, 184, 166, 0.15);
}
.bulk-ai-count-quick,
.bulk-ai-date-quick {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
}
.bulk-ai-count-quick .btn-glass-sm,
.bulk-ai-date-quick .btn-glass-sm {
    padding: 4px 10px;
    font-size: 0.78rem;
}

/* Excel'e yapıştırma 3-adım rehberi */
.bulk-ai-paste-guide {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 10px;
    padding: 16px;
}
.bulk-ai-step-card {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 8px;
    padding: 14px 12px;
    text-align: center;
    height: 100%;
    position: relative;
}
.bulk-ai-step-num {
    position: absolute;
    top: -10px;
    left: 12px;
    width: 26px;
    height: 26px;
    background: linear-gradient(135deg, #14b8a6, #0d9488);
    color: #fff;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.85rem;
    box-shadow: 0 2px 6px rgba(20, 184, 166, 0.4);
}
.bulk-ai-step-card i {
    font-size: 1.6rem;
    color: #14b8a6;
    display: block;
    margin: 6px 0 8px;
}
.bulk-ai-step-card strong {
    display: block;
    font-size: 0.9rem;
    color: #fff;
    margin-bottom: 6px;
}
.bulk-ai-step-card small {
    font-size: 0.78rem;
    line-height: 1.4;
}

@media (max-width: 768px) {
    .bulk-ai-media-opt,
    .bulk-ai-tone-opt { flex: 1 1 calc(50% - 8px); }
    .bulk-ai-count-quick,
    .bulk-ai-date-quick { flex-wrap: wrap; }
}

/* Hızlı başlangıç kartı — sayfa üstünde 3 yol özeti */
.bulk-quickstart-card {
    background: linear-gradient(135deg, rgba(20, 184, 166, 0.06), rgba(245, 158, 11, 0.04));
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-left: 3px solid #f59e0b;
    border-radius: 10px;
    padding: 16px 20px;
}
.bulk-quickstart-header {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.bulk-quickstart-header i { font-size: 1.2rem; }
.bulk-quickstart-header strong { color: #fff; font-size: 0.95rem; }
.bulk-quickstart-header small { font-size: 0.78rem; }

.bulk-quickstart-step {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 8px;
    padding: 12px 14px;
    height: 100%;
}
.bulk-quickstart-num {
    flex-shrink: 0;
    width: 28px;
    height: 28px;
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: #fff;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.9rem;
    box-shadow: 0 2px 5px rgba(245, 158, 11, 0.4);
}
.bulk-quickstart-step strong {
    display: block;
    color: #fff;
    font-size: 0.92rem;
    margin-bottom: 2px;
}
.bulk-quickstart-step small {
    line-height: 1.4;
    font-size: 0.78rem;
}
.bulk-quickstart-step code {
    background: rgba(255, 255, 255, 0.08);
    padding: 1px 5px;
    border-radius: 3px;
    font-size: 0.74rem;
}

/* Excel Önizleme Modal */
.bulk-preview-modal {
    background: rgba(15, 23, 42, 0.98);
    border: 1px solid rgba(255, 255, 255, 0.08);
    color: #e5e7eb;
}
.bulk-preview-stat {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 8px;
    padding: 14px 16px;
    text-align: center;
}
.bulk-preview-stat__label {
    display: block;
    font-size: 0.74rem;
    color: rgba(255, 255, 255, 0.6);
    text-transform: uppercase;
    letter-spacing: 0.4px;
    margin-bottom: 4px;
}
.bulk-preview-stat__value {
    display: block;
    font-size: 1.8rem;
    font-weight: 700;
    color: #fff;
    line-height: 1;
}
.bulk-preview-stat--total { border-left: 3px solid #3b82f6; }
.bulk-preview-stat--valid { border-left: 3px solid #14b8a6; }
.bulk-preview-stat--valid .bulk-preview-stat__value { color: #14b8a6; }
.bulk-preview-stat--error { border-left: 3px solid #ef4444; }
.bulk-preview-stat--error .bulk-preview-stat__value { color: #ef4444; }

.bulk-preview-table-wrap {
    max-height: 50vh;
    overflow-y: auto;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 8px;
}
.bulk-preview-table { font-size: 0.82rem; margin: 0; }
.bulk-preview-table thead {
    position: sticky;
    top: 0;
    background: rgba(0, 0, 0, 0.65);
    backdrop-filter: blur(8px);
    z-index: 1;
}
.bulk-preview-table th {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    padding: 8px 10px;
    white-space: nowrap;
}
.bulk-preview-table td {
    padding: 8px 10px;
    vertical-align: middle;
    white-space: nowrap;
    max-width: 180px;
    overflow: hidden;
    text-overflow: ellipsis;
}
.bulk-preview-table code {
    background: rgba(255, 255, 255, 0.08);
    padding: 1px 6px;
    border-radius: 3px;
    font-size: 0.74rem;
}

/* ────────── Konu Havuzu (AI Önerileri) ────────── */
.bulk-ai-topic-pool {
    background: rgba(245, 158, 11, 0.05);
    border: 1px dashed rgba(245, 158, 11, 0.3);
    border-radius: 8px;
    padding: 14px;
}
.bulk-ai-topic-controls {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
}
.bulk-ai-topic-theme {
    flex: 1 1 220px;
    min-width: 200px;
    background: rgba(0, 0, 0, 0.4);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: #e5e7eb;
}
.bulk-ai-topic-theme:focus {
    background: rgba(0, 0, 0, 0.5);
    border-color: rgba(245, 158, 11, 0.5);
    color: #fff;
    box-shadow: 0 0 0 0.2rem rgba(245, 158, 11, 0.15);
}
.bulk-ai-topic-actions {
    display: flex;
    gap: 6px;
}
.bulk-ai-topic-counter {
    margin-left: auto;
    font-size: 0.85rem;
    color: rgba(229, 231, 235, 0.75);
    background: rgba(255, 255, 255, 0.05);
    padding: 4px 10px;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.08);
}
.bulk-ai-topic-counter strong {
    color: #f59e0b;
    font-weight: 700;
}

.bulk-ai-topic-status {
    margin: 8px 0;
    padding: 10px 12px;
    border-radius: 6px;
    font-size: 0.875rem;
}
.bulk-ai-topic-status.is-info {
    background: rgba(59, 130, 246, 0.1);
    color: #93c5fd;
    border: 1px solid rgba(59, 130, 246, 0.25);
}
.bulk-ai-topic-status.is-error {
    background: rgba(239, 68, 68, 0.1);
    color: #fca5a5;
    border: 1px solid rgba(239, 68, 68, 0.25);
}

.bulk-ai-topic-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 6px;
    margin-top: 6px;
}
.bulk-ai-topic-chip {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 10px;
    background: rgba(0, 0, 0, 0.35);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.15s ease;
    margin: 0;
}
.bulk-ai-topic-chip:hover {
    background: rgba(0, 0, 0, 0.5);
    border-color: rgba(245, 158, 11, 0.3);
}
.bulk-ai-topic-chip.is-checked {
    background: rgba(245, 158, 11, 0.12);
    border-color: rgba(245, 158, 11, 0.55);
    box-shadow: inset 0 0 0 1px rgba(245, 158, 11, 0.25);
}
.bulk-ai-topic-chip input[type="checkbox"] {
    flex-shrink: 0;
    margin: 0;
    accent-color: #f59e0b;
    cursor: pointer;
}
.bulk-ai-topic-num {
    flex-shrink: 0;
    width: 22px;
    height: 22px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.06);
    color: rgba(229, 231, 235, 0.7);
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 600;
}
.bulk-ai-topic-chip.is-checked .bulk-ai-topic-num {
    background: rgba(245, 158, 11, 0.25);
    color: #fcd34d;
}
.bulk-ai-topic-text {
    flex: 1;
    color: #e5e7eb;
    font-size: 0.875rem;
    line-height: 1.35;
}

/* ────────── Özel Günler — Yıl Seçici ────────── */
.bulk-ai-specialdays-controls {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    margin-top: 10px;
    padding: 10px 12px;
    background: rgba(245, 158, 11, 0.08);
    border: 1px solid rgba(245, 158, 11, 0.25);
    border-radius: 6px;
}
.bulk-ai-specialdays-info {
    flex: 1 1 280px;
    color: #fcd34d;
    font-size: 0.875rem;
    line-height: 1.4;
}
.bulk-ai-specialdays-year-label {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #e5e7eb;
    font-size: 0.875rem;
    margin: 0;
}
.bulk-ai-specialdays-year {
    width: auto;
    min-width: 90px;
    background: rgba(0, 0, 0, 0.5);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: #e5e7eb;
}
.bulk-ai-specialdays-year:focus {
    background: rgba(0, 0, 0, 0.6);
    border-color: rgba(245, 158, 11, 0.5);
    color: #fff;
    box-shadow: 0 0 0 0.2rem rgba(245, 158, 11, 0.15);
}

/* ────────── TSV Yapıştır Card ────────── */
.bulk-tsv-card {
    border-left: 3px solid rgba(245, 158, 11, 0.55);
}
.bulk-tsv-input {
    background: rgba(0, 0, 0, 0.45);
    color: #d4d4d4;
    font-family: 'JetBrains Mono', 'Consolas', 'Monaco', monospace;
    font-size: 0.82rem;
    line-height: 1.5;
    border: 1px solid rgba(255, 255, 255, 0.08);
    white-space: pre;
}
.bulk-tsv-input:focus {
    background: rgba(0, 0, 0, 0.55);
    color: #fff;
    border-color: rgba(245, 158, 11, 0.5);
    box-shadow: 0 0 0 0.2rem rgba(245, 158, 11, 0.12);
}
.bulk-tsv-actions {
    align-items: center;
}
.bulk-tsv-actions form {
    display: flex;
    gap: 6px;
    align-items: center;
    flex-wrap: wrap;
}
.bulk-tsv-template-select {
    width: auto;
    min-width: 180px;
    background: rgba(0, 0, 0, 0.4);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: #e5e7eb;
}
.bulk-tsv-template-select:focus {
    background: rgba(0, 0, 0, 0.5);
    border-color: rgba(245, 158, 11, 0.4);
    color: #fff;
}
.bulk-tsv-actions button:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}
</style>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';
    var btn = document.getElementById('bulkAiPromptCopyBtn');
    var textarea = document.getElementById('bulkAiPromptText');
    if (! btn || ! textarea) return;

    var defaultLabel = btn.querySelector('[data-default]');
    var successLabel = btn.querySelector('[data-success]');
    var resetTimer = null;

    // ─── Prompt state management ───────────────────────────────
    var dataEl = document.getElementById('bulkAiPromptData');
    var promptData = null;
    try {
        promptData = dataEl ? JSON.parse(dataEl.textContent) : null;
    } catch (e) {
        promptData = null;
    }
    if (! promptData) return; // data yoksa modal yine açılır ama dinamik güncelleme olmaz

    var countInput     = document.getElementById('bulkAiCount');
    var startDateInput = document.getElementById('bulkAiStartDate');

    function unescapeTsv(text) {
        // PHP heredoc'ta \t ve \n literal kalmıştı; gerçek karakterlere çevir
        return String(text).replace(/\\t/g, '\t').replace(/\\n/g, '\n');
    }

    // ISO tarihi YYYY-MM-DD formatında, X gün ekleyerek
    function addDays(isoDate, days) {
        var d = new Date(isoDate + 'T00:00:00');
        if (isNaN(d.getTime())) d = new Date(); // fallback bugün
        d.setDate(d.getDate() + days);
        var y = d.getFullYear();
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var dd = String(d.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + dd;
    }

    function getCheckedValue(name, fallback) {
        var el = document.querySelector('input[name="' + name + '"]:checked');
        return el ? el.value : fallback;
    }

    function clampCount(value) {
        var n = parseInt(value, 10);
        if (isNaN(n) || n < 1) n = 1;
        var max = parseInt(countInput && countInput.max, 10) || {{ $maxRows }};
        if (n > max) n = max;
        return n;
    }

    function rebuildPrompt() {
        var media = getCheckedValue('bulkAiMedia', 'mixed');
        var tone  = getCheckedValue('bulkAiTone', 'samimi');
        var count = clampCount(countInput ? countInput.value : 30);
        var today = (startDateInput && startDateInput.value) || promptData.today || '{{ $today }}';

        var mediaIns = (promptData.mediaInstructions && promptData.mediaInstructions[media])
            || (promptData.mediaInstructions && promptData.mediaInstructions.mixed) || '';
        var toneIns  = (promptData.toneInstructions && promptData.toneInstructions[tone])
            || (promptData.toneInstructions && promptData.toneInstructions.samimi) || '';

        // Aktif preset (varsa) — preset.extra prompt'a satır olarak eklenir
        var activePreset = document.querySelector('.bulk-ai-preset-card.is-active');
        var presetKey = activePreset ? activePreset.getAttribute('data-preset') : null;
        var presetExtra = '';
        if (presetKey && promptData.presets && promptData.presets[presetKey] && promptData.presets[presetKey].extra) {
            presetExtra = '\n' + promptData.presets[presetKey].extra;
        }

        // SPECIALDAYS preset aktifse: preset.extra'ya yıllık takvim listesini ekle
        if (presetKey === 'specialdays') {
            presetExtra += buildSpecialDaysCalendarBlock();
        }

        // Konu havuzundan seçilen konular varsa prompt'a inject et
        var selectedTopics = getSelectedTopics();
        var topicBlock = '';
        if (selectedTopics.length > 0) {
            var topicLines = selectedTopics.map(function (t, i) {
                return '  ' + (i + 1) + '. ' + t;
            }).join('\n');
            topicBlock = '\n\n═══ ZORUNLU KONU LİSTESİ ═══\n'
                + 'AŞAĞIDAKİ ' + selectedTopics.length + ' KONUNUN HER BİRİ İÇİN BİR SATIR ÜRET — '
                + 'BAŞKA KONU EKLEME, SIRA DEĞİŞTİRME, ATLAMA YOK:\n\n'
                + topicLines
                + '\n\nHer konu D sütununa (konu) yazılacak; caption ve hashtag bu konuya UYGUN olacak.';
        }

        var rendered = String(promptData.template)
            .split('[TODAY]').join(today)
            .split('[TOMORROW]').join(addDays(today, 1))
            .split('[DAYAFTER]').join(addDays(today, 2))
            .split('[SAYI]').join(String(count))
            .replace('[MEDIA_INSTRUCTION]', mediaIns)
            .replace('[TONE_INSTRUCTION]', toneIns)
            .replace('[PRESET_EXTRA]', presetExtra)
            .replace('[TOPIC_LIST]', topicBlock);

        textarea.value = unescapeTsv(rendered);
    }

    // ─── Konu Havuzu (AI önerileri) ────────────────────────────
    var topicSuggestBtn = document.getElementById('bulkAiTopicSuggestBtn');
    var topicThemeInput = document.getElementById('bulkAiTopicTheme');
    var topicList       = document.getElementById('bulkAiTopicList');
    var topicStatus     = document.getElementById('bulkAiTopicStatus');
    var topicCounter    = document.getElementById('bulkAiTopicCounter');
    var topicSelCount   = document.getElementById('bulkAiTopicSelectedCount');
    var topicTotal      = document.getElementById('bulkAiTopicTotal');
    var topicSelectAll  = document.getElementById('bulkAiTopicSelectAllBtn');
    var topicClearAll   = document.getElementById('bulkAiTopicClearBtn');

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function getSelectedTopics() {
        if (! topicList) return [];
        var inputs = topicList.querySelectorAll('input[type="checkbox"]:checked');
        var arr = [];
        for (var i = 0; i < inputs.length; i++) arr.push(inputs[i].value);
        return arr;
    }

    function setTopicStatus(message, kind) {
        if (! topicStatus) return;
        if (! message) {
            topicStatus.hidden = true;
            topicStatus.innerHTML = '';
            topicStatus.className = 'bulk-ai-topic-status';
            return;
        }
        topicStatus.hidden = false;
        topicStatus.className = 'bulk-ai-topic-status is-' + (kind || 'info');
        topicStatus.innerHTML = message;
    }

    function updateTopicCounter() {
        if (! topicList) return;
        var allBoxes      = topicList.querySelectorAll('input[type="checkbox"]');
        var selectedBoxes = topicList.querySelectorAll('input[type="checkbox"]:checked');
        var totalCount    = allBoxes.length;
        var selectedCount = selectedBoxes.length;

        if (topicSelCount) topicSelCount.textContent = String(selectedCount);
        if (topicTotal)    topicTotal.textContent    = String(totalCount);
        if (topicCounter)  topicCounter.hidden       = totalCount === 0;
        if (topicSelectAll) topicSelectAll.hidden    = totalCount === 0;
        if (topicClearAll)  topicClearAll.hidden     = totalCount === 0;

        // Chip aktif görünümü
        topicList.querySelectorAll('.bulk-ai-topic-chip').forEach(function (chip) {
            var input = chip.querySelector('input[type="checkbox"]');
            chip.classList.toggle('is-checked', !! (input && input.checked));
        });

        // Seçim varsa Kaç satır? alanını eşitle
        if (selectedCount > 0 && countInput) {
            countInput.value = String(selectedCount);
        }

        rebuildPrompt();
    }

    function renderTopics(topics) {
        if (! topicList) return;
        if (! Array.isArray(topics) || topics.length === 0) {
            topicList.innerHTML = '';
            updateTopicCounter();
            return;
        }
        var html = topics.map(function (topic, idx) {
            var safe = escapeHtml(topic);
            return '<label class="bulk-ai-topic-chip">'
                + '<input type="checkbox" value="' + safe + '">'
                + '<span class="bulk-ai-topic-num">' + (idx + 1) + '</span>'
                + '<span class="bulk-ai-topic-text">' + safe + '</span>'
                + '</label>';
        }).join('');
        topicList.innerHTML = html;
        topicList.querySelectorAll('input[type="checkbox"]').forEach(function (inp) {
            inp.addEventListener('change', updateTopicCounter);
        });
        updateTopicCounter();
    }

    if (topicSuggestBtn) {
        topicSuggestBtn.addEventListener('click', function () {
            var defaultLabel = topicSuggestBtn.querySelector('[data-default]');
            var loadingLabel = topicSuggestBtn.querySelector('[data-loading]');
            topicSuggestBtn.disabled = true;
            if (defaultLabel) defaultLabel.classList.add('d-none');
            if (loadingLabel) loadingLabel.classList.remove('d-none');
            setTopicStatus('<i class="bi bi-arrow-repeat me-1"></i> AI 30 konu üretiyor (~10-15 sn)...', 'info');
            if (topicList) topicList.innerHTML = '';

            fetch('{{ route("admin.instagram-posts.bulk.topics") }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    count: 30,
                    theme: topicThemeInput ? topicThemeInput.value.trim() : ''
                })
            })
            .then(function (res) {
                return res.json().then(function (data) { return { ok: res.ok, data: data }; });
            })
            .then(function (payload) {
                if (! payload.ok || ! payload.data.success || ! Array.isArray(payload.data.topics) || payload.data.topics.length === 0) {
                    setTopicStatus('<i class="bi bi-exclamation-triangle me-1"></i> ' + escapeHtml(payload.data.message || 'Konu üretilemedi.'), 'error');
                    return;
                }
                setTopicStatus('', null);
                renderTopics(payload.data.topics);
            })
            .catch(function (e) {
                setTopicStatus('<i class="bi bi-exclamation-triangle me-1"></i> Bağlantı hatası: ' + escapeHtml(e.message), 'error');
            })
            .finally(function () {
                topicSuggestBtn.disabled = false;
                if (defaultLabel) defaultLabel.classList.remove('d-none');
                if (loadingLabel) loadingLabel.classList.add('d-none');
            });
        });
    }

    if (topicSelectAll) {
        topicSelectAll.addEventListener('click', function () {
            if (! topicList) return;
            topicList.querySelectorAll('input[type="checkbox"]').forEach(function (inp) {
                inp.checked = true;
            });
            updateTopicCounter();
        });
    }

    if (topicClearAll) {
        topicClearAll.addEventListener('click', function () {
            if (! topicList) return;
            topicList.querySelectorAll('input[type="checkbox"]').forEach(function (inp) {
                inp.checked = false;
            });
            updateTopicCounter();
        });
    }

    // ─── Aktif kart vurgulama helper'ları ──────────────────────
    function setActiveOption(selector, dataKey, value) {
        document.querySelectorAll(selector).forEach(function (el) {
            el.classList.toggle('is-active', el.getAttribute(dataKey) === value);
        });
    }

    // ─── Medya tipi radio değişimi ─────────────────────────────
    document.querySelectorAll('input[name="bulkAiMedia"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            if (! this.checked) return;
            setActiveOption('.bulk-ai-media-opt', 'data-media', this.value);
            // Manuel değişiklik preset highlight'ını kaldırır
            clearPresetActive();
            rebuildPrompt();
        });
    });

    // ─── Tonlama radio değişimi ───────────────────────────────
    document.querySelectorAll('input[name="bulkAiTone"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            if (! this.checked) return;
            setActiveOption('.bulk-ai-tone-opt', 'data-tone', this.value);
            clearPresetActive();
            rebuildPrompt();
        });
    });

    // ─── Satır sayısı input + hızlı butonlar ──────────────────
    if (countInput) {
        countInput.addEventListener('input', function () {
            clearPresetActive();
            rebuildPrompt();
        });
    }
    document.querySelectorAll('[data-count]').forEach(function (b) {
        b.addEventListener('click', function () {
            var val = parseInt(this.getAttribute('data-count'), 10);
            if (countInput) countInput.value = clampCount(val);
            clearPresetActive();
            rebuildPrompt();
        });
    });

    // ─── Tarih başlangıcı + Bugün/Yarın hızlı butonlar ────────
    if (startDateInput) {
        startDateInput.addEventListener('change', rebuildPrompt);
    }
    document.querySelectorAll('[data-date-offset]').forEach(function (b) {
        b.addEventListener('click', function () {
            var offset = parseInt(this.getAttribute('data-date-offset'), 10) || 0;
            var base = promptData.today || new Date().toISOString().slice(0, 10);
            if (startDateInput) startDateInput.value = addDays(base, offset);
            rebuildPrompt();
        });
    });

    // ─── Hızlı şablon preset uygulama ─────────────────────────
    function clearPresetActive() {
        document.querySelectorAll('.bulk-ai-preset-card').forEach(function (c) {
            c.classList.remove('is-active');
        });
    }
    function applyPreset(key) {
        var p = promptData.presets && promptData.presets[key];
        if (! p) return;

        // SPECIALDAYS özel davranış: yıl seçicinin görünürlüğü, count seçili yıla göre,
        // start date seçili yılın 1 Ocak'ı, satır sayısı = yıldaki özel gün sayısı
        var isSpecialDays = key === 'specialdays';
        toggleSpecialDaysControls(isSpecialDays);

        if (isSpecialDays) {
            var year = getSelectedSpecialYear();
            var dayCount = getSpecialDaysCount(year);
            if (countInput) countInput.value = clampCount(dayCount);
            // Tarih başlangıcını seçili yıla zorla
            if (startDateInput) startDateInput.value = year + '-01-01';
            updateSpecialDaysSummary(year, dayCount);
        } else {
            // 1. Satır sayısı (normal preset)
            if (countInput && p.count) countInput.value = clampCount(p.count);
        }

        // 2. Medya tipi radio
        var mediaRadio = document.querySelector('input[name="bulkAiMedia"][value="' + p.media + '"]');
        if (mediaRadio) {
            mediaRadio.checked = true;
            setActiveOption('.bulk-ai-media-opt', 'data-media', p.media);
        }

        // 3. Tonlama radio
        var toneRadio = document.querySelector('input[name="bulkAiTone"][value="' + p.tone + '"]');
        if (toneRadio) {
            toneRadio.checked = true;
            setActiveOption('.bulk-ai-tone-opt', 'data-tone', p.tone);
        }

        // 4. Preset kart vurgulama (extra talimat için rebuild'de okunur)
        clearPresetActive();
        var card = document.querySelector('.bulk-ai-preset-card[data-preset="' + key + '"]');
        if (card) card.classList.add('is-active');

        rebuildPrompt();
    }

    // ─── Özel Günler — yıl seçici + takvim ────────────────────
    var specialDaysControls = document.getElementById('bulkAiSpecialDaysControls');
    var specialDaysYearSel  = document.getElementById('bulkAiSpecialDaysYear');
    var specialDaysSummary  = document.getElementById('bulkAiSpecialDaysSummary');

    function toggleSpecialDaysControls(visible) {
        if (! specialDaysControls) return;
        specialDaysControls.hidden = ! visible;
    }

    function getSelectedSpecialYear() {
        var v = specialDaysYearSel && specialDaysYearSel.value
            ? parseInt(specialDaysYearSel.value, 10)
            : null;
        if (v && !isNaN(v)) return v;
        return (promptData.specialDays && promptData.specialDays.currentYear)
            ? Number(promptData.specialDays.currentYear)
            : new Date().getFullYear();
    }

    function getSpecialDaysForYear(year) {
        if (! promptData.specialDays || ! promptData.specialDays.byYear) return [];
        var arr = promptData.specialDays.byYear[year] || promptData.specialDays.byYear[String(year)];
        return Array.isArray(arr) ? arr : [];
    }

    function getSpecialDaysCount(year) {
        return getSpecialDaysForYear(year).length;
    }

    function updateSpecialDaysSummary(year, count) {
        if (! specialDaysSummary) return;
        if (count > 0) {
            specialDaysSummary.textContent = year + ' yılı için ' + count + ' özel gün takvime eklendi (sıra ve tarih sabit).';
        } else {
            specialDaysSummary.textContent = year + ' yılı için kayıtlı özel gün yok. config/turkish-special-days.php dosyasına ekle.';
        }
    }

    function buildSpecialDaysCalendarBlock() {
        var year = getSelectedSpecialYear();
        var days = getSpecialDaysForYear(year);
        if (! days.length) return '';

        var lines = days.map(function (d, i) {
            return '  ' + (i + 1) + '. ' + d.date
                + ' (' + (d.weekday || '') + ') → '
                + d.name
                + ' [' + (d.category || '') + ']'
                + (d.theme ? ' — ' + d.theme : '');
        }).join('\n');

        return '\n\n═══ ZORUNLU TAKVİM (' + year + ' YILI ÖZEL GÜNLERİ) ═══\n'
            + 'AŞAĞIDAKİ ' + days.length + ' SATIRIN HER BİRİ İÇİN BİRER POST ÜRET — '
            + 'TARİH/SIRA DEĞİŞTİRME, BİR ÖZEL GÜN ATLANMASIN, FAZLADAN GÜN EKLEME:\n\n'
            + lines
            + '\n\n- A sütunu (tarih) yukarıdaki YYYY-MM-DD değerini AYNEN kullan\n'
            + '- D sütunu (konu) "{Özel Gün Adı} – Çiftlikten" formatı\n'
            + '- E sütunu (caption) yukarıdaki "TEMA" notuna uygun, story için kısa (1-2 cümle)\n'
            + '- F sütunu (hashtags) özel günle ilgili 3-5 etiket\n'
            + '- I sütunu (facebook) = "hayir" (Story Facebook\'a paylaşılmaz)\n'
            + '- J sütunu (prompt_template) = "Özel Gün Hikaye" (varsa) — yoksa boş\n'
            + '- Saygı isteyen günlerde (Çanakkale, 10 Kasım, Atatürk anma) emoji kullanma, sade ol';
    }

    if (specialDaysYearSel) {
        specialDaysYearSel.addEventListener('change', function () {
            var year = getSelectedSpecialYear();
            var count = getSpecialDaysCount(year);
            if (countInput) countInput.value = clampCount(count);
            if (startDateInput) startDateInput.value = year + '-01-01';
            updateSpecialDaysSummary(year, count);
            rebuildPrompt();
        });
    }
    document.querySelectorAll('.bulk-ai-preset-card').forEach(function (c) {
        c.addEventListener('click', function (e) {
            e.preventDefault();
            applyPreset(this.getAttribute('data-preset'));
        });
    });

    // İlk yükleme: textarea zaten PHP'den dolu geldi, ama TSV escape için bir kez rebuild
    rebuildPrompt();

    btn.addEventListener('click', function () {
        // Modern clipboard API + fallback
        var copyPromise;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            copyPromise = navigator.clipboard.writeText(textarea.value);
        } else {
            textarea.select();
            try {
                document.execCommand('copy');
                copyPromise = Promise.resolve();
            } catch (e) {
                copyPromise = Promise.reject(e);
            }
        }

        copyPromise
            .then(function () {
                if (defaultLabel) defaultLabel.classList.add('d-none');
                if (successLabel) successLabel.classList.remove('d-none');
                btn.classList.remove('btn-teal');
                btn.classList.add('btn-success');
                if (resetTimer) clearTimeout(resetTimer);
                resetTimer = setTimeout(function () {
                    if (defaultLabel) defaultLabel.classList.remove('d-none');
                    if (successLabel) successLabel.classList.add('d-none');
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-teal');
                }, 2500);
            })
            .catch(function () {
                // Fallback: textarea seç + alert
                textarea.select();
                if (typeof AdminModal !== 'undefined') {
                    AdminModal.status({
                        title: 'Kopyalama desteklenmiyor',
                        message: 'Tarayıcı clipboard\'a yazma izin vermedi. Manuel olarak Ctrl+C ile kopyala.',
                        type: 'warning',
                    });
                }
            });
    });
})();

/* ──────────────────────────────────────────────────────────────
 *  Excel Önizleme — yükleme öncesi validation kontrolü
 *  AJAX: POST /admin/instagram-posts/bulk/preview
 * ──────────────────────────────────────────────────────────────*/
(function () {
    'use strict';
    var fileInput   = document.getElementById('bulkExcelFile');
    var previewBtn  = document.getElementById('bulkPreviewBtn');
    var modalEl     = document.getElementById('bulkPreviewModal');
    if (! fileInput || ! previewBtn || ! modalEl || typeof bootstrap === 'undefined') return;

    var loadingEl    = document.getElementById('bulkPreviewLoading');
    var contentEl    = document.getElementById('bulkPreviewContent');
    var parseErrEl   = document.getElementById('bulkPreviewParseError');
    var parseErrMsg  = document.getElementById('bulkPreviewParseErrorMsg');
    var totalEl      = document.getElementById('bulkPreviewTotal');
    var validEl      = document.getElementById('bulkPreviewValid');
    var errorEl      = document.getElementById('bulkPreviewError');
    var errorsBlock  = document.getElementById('bulkPreviewErrorsBlock');
    var errorsList   = document.getElementById('bulkPreviewErrorsList');
    var noValidEl    = document.getElementById('bulkPreviewNoValid');
    var tableWrap    = document.getElementById('bulkPreviewTableWrap');
    var rowsEl       = document.getElementById('bulkPreviewRows');
    var proceedBtn   = document.getElementById('bulkPreviewProceedBtn');

    var lastFingerprint = null;
    var bsModal = new bootstrap.Modal(modalEl);

    // File seçilince butonu aktif et
    fileInput.addEventListener('change', function () {
        previewBtn.disabled = ! (this.files && this.files.length > 0);
        // Yeni dosya seçince önceki cache'i unut
        lastFingerprint = null;
    });

    // Modal açılırken AJAX
    previewBtn.addEventListener('click', function () {
        if (! fileInput.files || fileInput.files.length === 0) return;

        var file = fileInput.files[0];
        var fp = file.name + '|' + file.size + '|' + file.lastModified;

        // Aynı dosyayı tekrar açıyorsa içeriği sıfırlama (UX iyileştirmesi)
        if (fp === lastFingerprint && contentEl && ! contentEl.classList.contains('d-none')) {
            return;
        }
        lastFingerprint = fp;

        showLoading();

        var token = document.querySelector('meta[name="csrf-token"]');
        var fd = new FormData();
        fd.append('excel_file', file);

        fetch('{{ route('admin.instagram-posts.bulk.preview') }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
            },
            body: fd,
        })
        .then(function (res) {
            return res.json().then(function (data) { return { ok: res.ok, data: data }; });
        })
        .then(function (result) {
            if (! result.ok || ! result.data || result.data.success === false) {
                var msg = (result.data && result.data.message)
                    ? result.data.message
                    : 'Excel parse edilemedi (HTTP ' + (result.data ? '' : '?') + ')';
                showParseError(msg);
                return;
            }
            renderResult(result.data);
        })
        .catch(function (err) {
            showParseError('İstek başarısız: ' + (err && err.message ? err.message : 'bilinmeyen hata'));
        });
    });

    function showLoading() {
        loadingEl.classList.remove('d-none');
        contentEl.classList.add('d-none');
        parseErrEl.classList.add('d-none');
    }

    function showParseError(msg) {
        loadingEl.classList.add('d-none');
        contentEl.classList.add('d-none');
        parseErrMsg.textContent = msg;
        parseErrEl.classList.remove('d-none');
    }

    function renderResult(data) {
        loadingEl.classList.add('d-none');
        parseErrEl.classList.add('d-none');
        contentEl.classList.remove('d-none');

        // Stats
        totalEl.textContent = data.total_rows || 0;
        validEl.textContent = data.valid_count || 0;
        errorEl.textContent = data.error_count || 0;

        // Errors
        if (data.errors && data.errors.length > 0) {
            errorsList.innerHTML = '';
            data.errors.forEach(function (err) {
                var li = document.createElement('li');
                var rowSpan = document.createElement('strong');
                rowSpan.textContent = 'Satır ' + (err.row || '?') + ': ';
                li.appendChild(rowSpan);
                li.appendChild(document.createTextNode(err.message || ''));
                errorsList.appendChild(li);
            });
            errorsBlock.classList.remove('d-none');
        } else {
            errorsBlock.classList.add('d-none');
        }

        // Hiç valid yoksa banner + tablo gizle
        if (! data.valid_count || data.valid_count === 0) {
            noValidEl.classList.remove('d-none');
            tableWrap.classList.add('d-none');
            proceedBtn.classList.add('d-none');
            return;
        }
        noValidEl.classList.add('d-none');

        // Preview rows tablosu
        rowsEl.innerHTML = '';
        (data.preview_rows || []).forEach(function (row) {
            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td class="text-muted">' + (row.row_number || '') + '</td>' +
                '<td>' + esc(row.tarih) + '</td>' +
                '<td>' + esc(row.saat) + '</td>' +
                '<td><span class="usr-status-badge">' + esc(row.tip) + '</span></td>' +
                '<td>' + esc(row.konu || '—') + '</td>' +
                '<td>' + esc(row.caption || '—') + '</td>' +
                '<td><small>' + esc(row.hashtags || '—') + '</small></td>' +
                '<td><code>' + esc(row.gorsel_kaynagi) + '</code></td>' +
                '<td><small>' + esc(row.gorsel_degeri || '—') + '</small></td>' +
                '<td>' + esc(row.facebook) + '</td>' +
                '<td><small>' + esc(row.prompt_template || '—') + '</small></td>';
            rowsEl.appendChild(tr);
        });
        tableWrap.classList.remove('d-none');
        proceedBtn.classList.remove('d-none');
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
})();

/* ──────────────────────────────────────────────────────────────
 *  AI Çıktısı (TSV) → Önizle / Excel İndir / Doğrudan İçe Aktar
 *  Endpoints:
 *    POST /admin/instagram-posts/bulk/from-tsv/preview  (JSON)
 *    POST /admin/instagram-posts/bulk/from-tsv/download (xlsx stream)
 *    POST /admin/instagram-posts/bulk/from-tsv/import   (redirect)
 * ──────────────────────────────────────────────────────────────*/
(function () {
    'use strict';
    var input        = document.getElementById('bulkTsvInput');
    var charCount    = document.getElementById('bulkTsvCharCount');
    var previewBtn   = document.getElementById('bulkTsvPreviewBtn');
    var downloadBtn  = document.getElementById('bulkTsvDownloadBtn');
    var importBtn    = document.getElementById('bulkTsvImportBtn');
    var downloadFld  = document.getElementById('bulkTsvDownloadField');
    var importFld    = document.getElementById('bulkTsvImportField');
    var downloadForm = document.getElementById('bulkTsvDownloadForm');
    var importForm   = document.getElementById('bulkTsvImportForm');

    if (! input || ! previewBtn || typeof bootstrap === 'undefined') return;

    // Aynı modal'ı yeniden kullan (Excel önizleme ile aynı UI)
    var modalEl     = document.getElementById('bulkPreviewModal');
    var loadingEl   = document.getElementById('bulkPreviewLoading');
    var contentEl   = document.getElementById('bulkPreviewContent');
    var parseErrEl  = document.getElementById('bulkPreviewParseError');
    var parseErrMsg = document.getElementById('bulkPreviewParseErrorMsg');
    var totalEl     = document.getElementById('bulkPreviewTotal');
    var validEl     = document.getElementById('bulkPreviewValid');
    var errorEl     = document.getElementById('bulkPreviewError');
    var errorsBlock = document.getElementById('bulkPreviewErrorsBlock');
    var errorsList  = document.getElementById('bulkPreviewErrorsList');
    var noValidEl   = document.getElementById('bulkPreviewNoValid');
    var tableWrap   = document.getElementById('bulkPreviewTableWrap');
    var rowsEl      = document.getElementById('bulkPreviewRows');
    var proceedBtn  = document.getElementById('bulkPreviewProceedBtn');

    // Karakter sayacı + buton durumları
    function updateState() {
        var content = (input.value || '').trim();
        var hasContent = content.length > 0;

        if (charCount) {
            charCount.textContent = content.length.toLocaleString('tr-TR') + ' karakter';
        }
        previewBtn.disabled  = ! hasContent;
        downloadBtn.disabled = ! hasContent;
        importBtn.disabled   = ! hasContent;
    }

    input.addEventListener('input', updateState);
    input.addEventListener('paste', function () {
        // Paste sonrası bir tick bekle, değer settle olsun
        setTimeout(updateState, 0);
    });
    updateState();

    // Form submit — textarea değerini hidden field'lara aktar
    if (downloadForm) {
        downloadForm.addEventListener('submit', function (e) {
            if (! (input.value || '').trim()) {
                e.preventDefault();
                return;
            }
            downloadFld.value = input.value;
        });
    }
    if (importForm) {
        importForm.addEventListener('submit', function (e) {
            if (! (input.value || '').trim()) {
                e.preventDefault();
                return;
            }
            importFld.value = input.value;
            // Çift tıklamayı engelle
            importBtn.disabled = true;
            importBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> İşleniyor...';
        });
    }

    // Önizle butonu — modal'ı aç + AJAX
    previewBtn.addEventListener('click', function () {
        if (! (input.value || '').trim()) return;

        showLoading();

        var token = document.querySelector('meta[name="csrf-token"]');
        fetch('{{ route('admin.instagram-posts.bulk.from-tsv.preview') }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
            },
            body: JSON.stringify({ tsv: input.value }),
        })
        .then(function (res) {
            return res.json().then(function (data) { return { ok: res.ok, data: data }; });
        })
        .then(function (result) {
            if (! result.ok || ! result.data || result.data.success === false) {
                var msg = (result.data && result.data.message)
                    ? result.data.message
                    : 'TSV parse edilemedi.';
                showParseError(msg);
                return;
            }
            renderResult(result.data);
        })
        .catch(function (err) {
            showParseError('İstek başarısız: ' + (err && err.message ? err.message : 'bilinmeyen hata'));
        });
    });

    function showLoading() {
        if (loadingEl) loadingEl.classList.remove('d-none');
        if (contentEl) contentEl.classList.add('d-none');
        if (parseErrEl) parseErrEl.classList.add('d-none');
    }

    function showParseError(msg) {
        if (loadingEl) loadingEl.classList.add('d-none');
        if (contentEl) contentEl.classList.add('d-none');
        if (parseErrMsg) parseErrMsg.textContent = msg;
        if (parseErrEl) parseErrEl.classList.remove('d-none');
    }

    function renderResult(data) {
        if (loadingEl) loadingEl.classList.add('d-none');
        if (parseErrEl) parseErrEl.classList.add('d-none');
        if (contentEl) contentEl.classList.remove('d-none');

        if (totalEl) totalEl.textContent = data.total_rows || 0;
        if (validEl) validEl.textContent = data.valid_count || 0;
        if (errorEl) errorEl.textContent = data.error_count || 0;

        if (errorsList && errorsBlock) {
            if (data.errors && data.errors.length > 0) {
                errorsList.innerHTML = '';
                data.errors.forEach(function (err) {
                    var li = document.createElement('li');
                    var rowSpan = document.createElement('strong');
                    rowSpan.textContent = 'Satır ' + (err.row || '?') + ': ';
                    li.appendChild(rowSpan);
                    li.appendChild(document.createTextNode(err.message || ''));
                    errorsList.appendChild(li);
                });
                errorsBlock.classList.remove('d-none');
            } else {
                errorsBlock.classList.add('d-none');
            }
        }

        if (! data.valid_count || data.valid_count === 0) {
            if (noValidEl) noValidEl.classList.remove('d-none');
            if (tableWrap) tableWrap.classList.add('d-none');
            if (proceedBtn) proceedBtn.classList.add('d-none');
            return;
        }
        if (noValidEl) noValidEl.classList.add('d-none');

        if (rowsEl) {
            rowsEl.innerHTML = '';
            (data.preview_rows || []).forEach(function (row) {
                var tr = document.createElement('tr');
                tr.innerHTML =
                    '<td class="text-muted">' + (row.row_number || '') + '</td>' +
                    '<td>' + escTsv(row.tarih) + '</td>' +
                    '<td>' + escTsv(row.saat) + '</td>' +
                    '<td><span class="usr-status-badge">' + escTsv(row.tip) + '</span></td>' +
                    '<td>' + escTsv(row.konu || '—') + '</td>' +
                    '<td>' + escTsv(row.caption || '—') + '</td>' +
                    '<td><small>' + escTsv(row.hashtags || '—') + '</small></td>' +
                    '<td><code>' + escTsv(row.gorsel_kaynagi) + '</code></td>' +
                    '<td><small>' + escTsv(row.gorsel_degeri || '—') + '</small></td>' +
                    '<td>' + escTsv(row.facebook) + '</td>' +
                    '<td><small>' + escTsv(row.prompt_template || '—') + '</small></td>';
                rowsEl.appendChild(tr);
            });
        }
        if (tableWrap) tableWrap.classList.remove('d-none');
        // proceedBtn TSV akışında kullanılmıyor → gizle
        if (proceedBtn) proceedBtn.classList.add('d-none');
    }

    function escTsv(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
})();

/* ──────────────────────────────────────────────────────────────
 *  KÜTÜPHANEDEN PLAN OLUŞTUR — Faz 4 Görsel-İlk Akış
 *  POST /admin/gorsel-kutuphanesi/plan/olustur (preview)
 *  POST /admin/gorsel-kutuphanesi/plan/uygula (execute)
 * ──────────────────────────────────────────────────────────────*/
(function () {
    'use strict';
    var modal = document.getElementById('bulkLibraryPlanModal');
    if (! modal) return;

    var csrf = document.querySelector('meta[name="csrf-token"]');
    var token = csrf ? csrf.getAttribute('content') : '';

    var form        = document.getElementById('bulkPlanForm');
    var previewBtn  = document.getElementById('bulkPlanPreviewBtn');
    var executeBtn  = document.getElementById('bulkPlanExecuteBtn');
    var resultEl    = document.getElementById('bulkPlanResult');
    var pctSumEl    = document.getElementById('bulkPlanPctSum');

    var feedInput  = document.getElementById('bulkPlanFeedPct');
    var storyInput = document.getElementById('bulkPlanStoryPct');
    var reelsInput = document.getElementById('bulkPlanReelsPct');

    var currentPlan = null; // Preview sonrası dolar, execute'ta kullanılır

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function updatePctSum() {
        var sum = (parseFloat(feedInput.value) || 0) + (parseFloat(storyInput.value) || 0) + (parseFloat(reelsInput.value) || 0);
        pctSumEl.innerHTML = 'Toplam: <strong>' + sum + '%</strong>';
        if (Math.abs(sum - 100) > 5) {
            pctSumEl.classList.add('text-danger');
            pctSumEl.classList.remove('text-success');
        } else {
            pctSumEl.classList.remove('text-danger');
            pctSumEl.classList.add('text-success');
        }
    }

    [feedInput, storyInput, reelsInput].forEach(function (i) {
        if (i) i.addEventListener('input', updatePctSum);
    });

    if (previewBtn) {
        previewBtn.addEventListener('click', function () {
            var defLbl  = previewBtn.querySelector('[data-default]');
            var loadLbl = previewBtn.querySelector('[data-loading]');
            previewBtn.disabled = true;
            if (defLbl) defLbl.classList.add('d-none');
            if (loadLbl) loadLbl.classList.remove('d-none');
            resultEl.innerHTML = '';
            executeBtn.disabled = true;
            currentPlan = null;

            var fd = new FormData(form);
            // Yüzdeleri 0-1 aralığına çevir
            var feedPct  = (parseFloat(fd.get('feed_pct'))  || 0) / 100;
            var storyPct = (parseFloat(fd.get('story_pct')) || 0) / 100;
            var reelsPct = (parseFloat(fd.get('reels_pct')) || 0) / 100;

            var payload = {
                start_date: fd.get('start_date'),
                end_date: fd.get('end_date'),
                post_per_day: fd.get('post_per_day'),
                feed_pct: feedPct,
                story_pct: storyPct,
                reels_pct: reelsPct,
                theme: fd.get('theme'),
                mood: fd.get('mood'),
                season: fd.get('season'),
            };

            fetch('{{ route('admin.image-library.build-plan') }}', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify(payload),
            })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
            .then(function (r) {
                if (! r.ok || ! r.data.success) {
                    resultEl.innerHTML = '<div class="alert alert-danger">' + escapeHtml(r.data.message || 'Plan oluşturulamadı') + '</div>';
                    return;
                }
                currentPlan = r.data.plan;
                renderPlanPreview(r.data);
                executeBtn.disabled = false;
            })
            .catch(function (e) {
                resultEl.innerHTML = '<div class="alert alert-danger">İstek başarısız: ' + escapeHtml(e.message) + '</div>';
            })
            .finally(function () {
                previewBtn.disabled = false;
                if (defLbl) defLbl.classList.remove('d-none');
                if (loadLbl) loadLbl.classList.add('d-none');
            });
        });
    }

    function renderPlanPreview(data) {
        var html = '';
        html += '<div class="alert alert-success mb-3"><i class="bi bi-check-circle me-1"></i> ' + escapeHtml(data.message) + '</div>';

        // Eksik bildirimi
        if (data.stats.insufficient && Object.keys(data.stats.insufficient).length) {
            html += '<div class="alert alert-warning small">';
            html += '<strong><i class="bi bi-exclamation-triangle me-1"></i> Yetersiz galeri:</strong> ';
            Object.keys(data.stats.insufficient).forEach(function (type) {
                var s = data.stats.insufficient[type];
                html += type + ' (' + s.available + '/' + s.needed + '), ';
            });
            html += ' — recycle yapıldı.';
            html += '</div>';
        }

        html += '<div class="table-responsive" style="max-height:350px;overflow-y:auto;">';
        html += '<table class="table table-sm bulk-plan-table"><thead><tr><th>#</th><th>Tarih</th><th>Saat</th><th>Tip</th><th>Görsel</th><th>Konu</th></tr></thead><tbody>';
        data.plan.forEach(function (row, i) {
            html += '<tr>';
            html += '<td class="text-muted">' + (i + 1) + '</td>';
            html += '<td><code>' + escapeHtml(row.date) + '</code></td>';
            html += '<td>' + escapeHtml(row.time) + '</td>';
            html += '<td><span class="badge bg-secondary">' + escapeHtml(row.media_type) + '</span></td>';
            html += '<td><img src="' + escapeHtml(row.image_url) + '" style="width:48px;height:48px;object-fit:cover;border-radius:4px;" loading="lazy"></td>';
            html += '<td><small>' + escapeHtml(row.suggested_konu || '—') + '</small></td>';
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        resultEl.innerHTML = html;
    }

    if (executeBtn) {
        executeBtn.addEventListener('click', function () {
            if (! currentPlan || ! currentPlan.length) return;
            if (! confirm(currentPlan.length + ' post oluşturulacak. Onaylıyor musun?')) return;

            var defLbl  = executeBtn.querySelector('[data-default]');
            var loadLbl = executeBtn.querySelector('[data-loading]');
            executeBtn.disabled = true;
            if (defLbl) defLbl.classList.add('d-none');
            if (loadLbl) loadLbl.classList.remove('d-none');

            fetch('{{ route('admin.image-library.execute-plan') }}', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ plan: currentPlan }),
            })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
            .then(function (r) {
                if (! r.ok || ! r.data.success) {
                    alert(r.data.message || 'Plan uygulanamadı');
                    return;
                }
                if (r.data.redirect) {
                    window.location.href = r.data.redirect;
                }
            })
            .catch(function (e) { alert('İstek başarısız: ' + e.message); })
            .finally(function () {
                executeBtn.disabled = false;
                if (defLbl) defLbl.classList.remove('d-none');
                if (loadLbl) loadLbl.classList.add('d-none');
            });
        });
    }
})();
</script>
@endpush
