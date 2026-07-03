@extends('layouts.admin')

@section('title', 'AI İçerik Ayarları')

@section('content')

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">AI İçerik Ayarları</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" data-aos="fade-down">
        <div>
            <h1 class="page-title"><i class="bi bi-sliders2 me-2"></i>AI İçerik Ayarları</h1>
            <p class="page-subtitle">Gemini AI ile otomatik blog içeriği üretiminde kullanılan kuralları ve ürün listesini yönet</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.ai-logs.index') }}" class="btn-glass">
                <i class="bi bi-clock-history me-1"></i> AI Logları
            </a>
            <button type="button" class="btn-glass" data-bs-toggle="modal" data-bs-target="#resetModal">
                <i class="bi bi-arrow-counterclockwise me-1"></i> Varsayılana Dön
            </button>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="alert alert-success mb-4" data-aos="fade-up">
            <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger mb-4" data-aos="fade-up">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <strong>Lütfen aşağıdaki hataları düzeltin:</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.ai-prompt-settings.update') }}" method="POST" id="aiSettingsForm">
        @csrf
        @method('PUT')

        <div class="row g-4">
            {{-- Sol: Prompt Editör --}}
            <div class="col-xl-8" data-aos="fade-up">
                {{-- System Instruction --}}
                <div class="card-dark mb-4">
                    <div class="card-header-custom">
                        <h6><i class="bi bi-chat-square-text me-2 text-teal"></i>Sistem Talimatı (System Instruction)</h6>
                    </div>
                    <div class="card-body-custom">
                        <div class="stg-field">
                            <label class="stg-label" for="system_instruction">AI'a verilen rol ve kurallar</label>
                            <textarea
                                id="system_instruction"
                                name="system_instruction"
                                rows="20"
                                class="stg-input ai-prompt-textarea @error('system_instruction') is-invalid @enderror"
                                placeholder="AI'ya verilen rol tanımı ve kurallar...">{{ old('system_instruction', $setting->system_instruction) }}</textarea>
                            @error('system_instruction')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <p class="stg-hint mt-2">
                                <i class="bi bi-info-circle me-1"></i>
                                Kullanılabilir yer tutucular:
                                <code>{product}</code> (seçilen ürün adı),
                                <code>{max_words}</code> (maksimum kelime sayısı)
                            </p>
                        </div>
                    </div>
                </div>

                {{-- User Prompt --}}
                <div class="card-dark mb-4">
                    <div class="card-header-custom">
                        <h6><i class="bi bi-chat-left-dots me-2 text-teal"></i>Kullanıcı Promptu (User Prompt)</h6>
                    </div>
                    <div class="card-body-custom">
                        <div class="stg-field">
                            <label class="stg-label" for="user_prompt">Çıktı format ve şartları</label>
                            <textarea
                                id="user_prompt"
                                name="user_prompt"
                                rows="12"
                                class="stg-input ai-prompt-textarea @error('user_prompt') is-invalid @enderror"
                                placeholder="Çıktı formatı ve JSON anahtarları...">{{ old('user_prompt', $setting->user_prompt) }}</textarea>
                            @error('user_prompt')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <p class="stg-hint mt-2">
                                <i class="bi bi-info-circle me-1"></i>
                                JSON çıktı şartlarını ve döneceği anahtarları (<code>title</code>, <code>content</code>, <code>description</code>) burada tanımla.
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Save bar --}}
                <div class="card-dark mb-4">
                    <div class="card-body-custom">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div class="stg-toggle-item border-0 p-0">
                                <div class="stg-toggle-info">
                                    <span>Otomatik Üretim Aktif</span>
                                    <small>Pasif olduğunda cron job içerik üretmez</small>
                                </div>
                                <label class="stg-switch">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $setting->is_active) ? 'checked' : '' }}>
                                    <span class="stg-switch-slider"></span>
                                </label>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn-teal">
                                    <i class="bi bi-check-lg me-1"></i> Kaydet
                                </button>
                                <a href="{{ route('admin.dashboard') }}" class="btn-glass">
                                    <i class="bi bi-x-lg me-1"></i> İptal
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Sağ: Genel Ayarlar + Ürün Listesi --}}
            <div class="col-xl-4" data-aos="fade-up" data-aos-delay="100">
                {{-- Genel Ayarlar --}}
                <div class="card-dark mb-4">
                    <div class="card-header-custom">
                        <h6><i class="bi bi-gear me-2 text-teal"></i>Genel Ayarlar</h6>
                    </div>
                    <div class="card-body-custom">
                        <div class="stg-field mb-3">
                            <label class="stg-label" for="max_words">Maksimum Kelime Sayısı</label>
                            <input type="number"
                                   class="stg-input @error('max_words') is-invalid @enderror"
                                   id="max_words"
                                   name="max_words"
                                   min="100"
                                   max="5000"
                                   value="{{ old('max_words', $setting->max_words) }}">
                            @error('max_words')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <p class="stg-hint">Üretilen içeriğin maksimum kelime sayısı (sistem talimatına otomatik aktarılır).</p>
                        </div>

                        <div class="stg-field">
                            <label class="stg-label" for="category_slug">Hedef Blog Kategorisi (Slug)</label>
                            <input type="text"
                                   class="stg-input @error('category_slug') is-invalid @enderror"
                                   id="category_slug"
                                   name="category_slug"
                                   value="{{ old('category_slug', $setting->category_slug) }}"
                                   placeholder="urunlerimiz">
                            @error('category_slug')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <p class="stg-hint">Üretilen yazıların atanacağı blog kategorisinin slug değeri.</p>
                        </div>
                    </div>
                </div>

                {{-- Ürün Listesi --}}
                <div class="card-dark mb-4">
                    <div class="card-header-custom d-flex align-items-center justify-content-between">
                        <h6 class="mb-0"><i class="bi bi-box-seam me-2 text-teal"></i>Ürün Listesi</h6>
                        <button type="button" class="btn-glass btn-sm" id="addProductBtn">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                    <div class="card-body-custom">
                        <p class="stg-hint mb-3">
                            <i class="bi bi-info-circle me-1"></i>
                            AI her çalıştığında bu listeden rastgele bir ürün seçerek içerik üretir.
                        </p>
                        <div id="productsList" class="ai-products-list">
                            @foreach(old('products', $setting->products ?? []) as $index => $product)
                                <div class="ai-product-row">
                                    <input type="text"
                                           name="products[]"
                                           class="stg-input"
                                           value="{{ $product }}"
                                           placeholder="Ürün adı">
                                    <button type="button" class="ai-product-remove" title="Sil">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                        @error('products')
                            <div class="invalid-feedback d-block mt-2">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                {{-- Hedef Şehir Listesi (Modül 2: Blog Şehir Rotasyonu) --}}
                <div class="card-dark mb-4">
                    <div class="card-header-custom d-flex align-items-center justify-content-between">
                        <h6 class="mb-0"><i class="bi bi-geo-alt me-2 text-teal"></i>Hedef Şehir Listesi</h6>
                        <button type="button" class="btn-glass btn-sm" id="addCityBtn">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                    <div class="card-body-custom">
                        <p class="stg-hint mb-3">
                            <i class="bi bi-info-circle me-1"></i>
                            AI her çalıştığında bu listeden sıradaki şehri seçer (round-robin) ve yazıyı o şehre yönelik anahtar kelimelerle zenginleştirir. Boş bırakılırsa hiçbir yazıda şehir hedeflenmez.
                        </p>
                        <div id="citiesList" class="ai-products-list">
                            @foreach(old('target_cities', $setting->target_cities ?? []) as $index => $city)
                                <div class="ai-product-row">
                                    <input type="text"
                                           name="target_cities[]"
                                           class="stg-input"
                                           value="{{ $city }}"
                                           placeholder="Şehir adı (örn: İstanbul)">
                                    <button type="button" class="ai-product-remove" title="Sil">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                        @error('target_cities')
                            <div class="invalid-feedback d-block mt-2">{{ $message }}</div>
                        @enderror

                        <hr class="my-3">

                        <div class="stg-field mb-3">
                            <label class="stg-label" for="general_post_every_n_runs">Genel Yazı Sıklığı</label>
                            <input type="number"
                                   class="stg-input @error('general_post_every_n_runs') is-invalid @enderror"
                                   id="general_post_every_n_runs"
                                   name="general_post_every_n_runs"
                                   min="0"
                                   max="200"
                                   value="{{ old('general_post_every_n_runs', $setting->general_post_every_n_runs ?? 28) }}">
                            @error('general_post_every_n_runs')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <p class="stg-hint">
                                Kaç çalıştırmada bir kez şehirsiz "genel" yazı üretilsin? Cron günde 4 kez çalışır:
                                <strong>28</strong> = haftada 1, <strong>4</strong> = günde 1, <strong>0</strong> = hiç.
                            </p>
                        </div>

                        <div class="ai-info-row">
                            <span class="text-muted">Sıradaki şehir:</span>
                            @php
                                $cities = $setting->target_cities ?? [];
                                $every  = (int) ($setting->general_post_every_n_runs ?? 0);
                                $idx    = (int) ($setting->city_rotation_index ?? 0);
                                $isGeneric = $every > 0 && $idx > 0 && $idx % $every === 0;
                                $next   = (! empty($cities) && ! $isGeneric)
                                    ? $cities[$idx % count($cities)]
                                    : null;
                            @endphp
                            <span class="fw-semibold">
                                {{ $next ?? 'Genel yazı (şehirsiz)' }}
                            </span>
                        </div>
                        <div class="ai-info-row">
                            <span class="text-muted">Rotasyon sayacı:</span>
                            <span class="fw-semibold">{{ $idx }}</span>
                        </div>

                        <button type="button"
                                class="btn-glass btn-sm w-100 mt-3"
                                data-bs-toggle="modal"
                                data-bs-target="#resetRotationModal">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Sayacı Sıfırla
                        </button>
                    </div>
                </div>

                {{-- Bilgi Kartı --}}
                <div class="card-dark">
                    <div class="card-header-custom">
                        <h6><i class="bi bi-lightbulb me-2 text-teal"></i>Bilgi</h6>
                    </div>
                    <div class="card-body-custom">
                        <div class="ai-info-row">
                            <span class="text-muted">Cron çalışma sıklığı:</span>
                            <span class="fw-semibold">Günde 4 kez</span>
                        </div>
                        <div class="ai-info-row">
                            <span class="text-muted">Komut:</span>
                            <code>php artisan blog:generate</code>
                        </div>
                        <div class="ai-info-row">
                            <span class="text-muted">Son güncelleme:</span>
                            <span>{{ $setting->updated_at?->format('d.m.Y H:i') ?? '-' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    {{-- Reset Rotation Modal --}}
    <div class="modal fade" id="resetRotationModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-4">
                    <div class="mt-modal-icon mt-modal-icon--warning mb-3">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </div>
                    <h5 class="mb-2">Şehir Rotasyonu Sayacını Sıfırla</h5>
                    <p class="text-muted">
                        Sıradaki blog yazısı, hedef şehir listesinin <strong>ilk sırasından</strong> başlayacak.
                        Bu işlem yalnızca rotasyon sayacını sıfırlar; mevcut blog yazıları etkilenmez.
                    </p>
                    <div class="d-flex gap-2 justify-content-center mt-4">
                        <button type="button" class="btn-glass" data-bs-dismiss="modal">İptal</button>
                        <form action="{{ route('admin.ai-prompt-settings.reset-rotation') }}" method="POST">
                            @csrf
                            <button type="submit" class="btn-teal">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Sıfırla
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Reset Modal --}}
    <div class="modal fade" id="resetModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-4">
                    <div class="mt-modal-icon mt-modal-icon--warning mb-3">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </div>
                    <h5 class="mb-2">Varsayılana Sıfırla</h5>
                    <p class="text-muted">
                        Sistem talimatı, kullanıcı promptu, ürün listesi ve diğer ayarlar varsayılan haline döndürülecek.
                        Bu işlem geri alınamaz.
                    </p>
                    <div class="d-flex gap-2 justify-content-center mt-4">
                        <button type="button" class="btn-glass" data-bs-dismiss="modal">İptal</button>
                        <form action="{{ route('admin.ai-prompt-settings.reset') }}" method="POST">
                            @csrf
                            <button type="submit" class="btn-teal">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Sıfırla
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('styles')
<style>
.ai-prompt-textarea{font-family:'SF Mono','Monaco','Cascadia Code','Roboto Mono',monospace;font-size:13px;line-height:1.6;resize:vertical;min-height:200px}
.ai-products-list{display:flex;flex-direction:column;gap:8px;max-height:420px;overflow-y:auto;padding-right:4px}
.ai-product-row{display:flex;gap:6px;align-items:center}
.ai-product-row .stg-input{flex:1}
.ai-product-remove{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#ef4444;border-radius:8px;width:36px;height:36px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:.2s;flex-shrink:0}
.ai-product-remove:hover{background:rgba(239,68,68,.2);transform:scale(1.05)}
.ai-info-row{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid color-mix(in srgb, var(--border-color) 40%, transparent);font-size:13px;gap:8px}
.ai-info-row:last-child{border-bottom:none}
.ai-info-row code{background:var(--bg-input);padding:2px 6px;border-radius:4px;font-size:12px;color:var(--text-primary)}
</style>
@endpush

@push('scripts')
<script>
(function() {
    /**
     * Generic dynamic-list helper used for both products and target cities.
     * @param {string} listId
     * @param {string} addBtnId
     * @param {string} fieldName
     * @param {string} placeholder
     * @param {boolean} keepEmptyFallbackRow Whether to always keep at least 1 row.
     */
    function bindDynamicList(listId, addBtnId, fieldName, placeholder, keepEmptyFallbackRow) {
        const list = document.getElementById(listId);
        const addBtn = document.getElementById(addBtnId);
        if (!list || !addBtn) return;

        function addRow(value) {
            const row = document.createElement('div');
            row.className = 'ai-product-row';
            row.innerHTML = `
                <input type="text" name="${fieldName}" class="stg-input" value="${value || ''}" placeholder="${placeholder}">
                <button type="button" class="ai-product-remove" title="Sil"><i class="bi bi-x-lg"></i></button>
            `;
            list.appendChild(row);
            row.querySelector('input').focus();
        }

        addBtn.addEventListener('click', function() { addRow(''); });

        list.addEventListener('click', function(e) {
            const btn = e.target.closest('.ai-product-remove');
            if (!btn) return;
            const rows = list.querySelectorAll('.ai-product-row');
            if (keepEmptyFallbackRow && rows.length <= 1) {
                btn.closest('.ai-product-row').querySelector('input').value = '';
                return;
            }
            btn.closest('.ai-product-row').remove();
        });

        if (keepEmptyFallbackRow && list.children.length === 0) {
            addRow('');
        }
    }

    bindDynamicList('productsList', 'addProductBtn', 'products[]', 'Ürün adı', true);
    bindDynamicList('citiesList',   'addCityBtn',    'target_cities[]', 'Şehir adı (örn: İstanbul)', false);
})();
</script>
@endpush
