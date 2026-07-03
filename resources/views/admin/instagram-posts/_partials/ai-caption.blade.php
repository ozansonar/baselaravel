@php
    /** @var bool $isPublished */
    /** @var \Illuminate\Database\Eloquent\Collection $products */
@endphp

@if(! $isPublished)
<div class="card-dark mb-4 ig-ai-block">
    <div class="card-header-custom">
        <div class="form-section-header mb-0">
            <div class="form-section-icon bg-icon-purple"><i class="bi bi-stars"></i></div>
            <div>
                <h6 class="mb-0">AI ile Caption Üret</h6>
                <small class="text-muted">Gemini AI Instagram'a uygun caption + hashtag öneriyor (~30 sn)</small>
            </div>
        </div>
    </div>
    <div class="card-body-custom">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Ürün Seç (opsiyonel)</label>
                <select id="aiProductSelect" class="form-control">
                    <option value="">— Ürün seçilmedi —</option>
                    @foreach($products ?? [] as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </select>
                <div class="form-text">Ürün seçersen ürün adı/açıklaması context olur</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Tonlama</label>
                <select id="aiToneSelect" class="form-control">
                    <option value="samimi ve doğal">Samimi ve doğal</option>
                    <option value="profesyonel">Profesyonel</option>
                    <option value="eğlenceli ve enerjik">Eğlenceli ve enerjik</option>
                    <option value="nostaljik ve duygusal">Nostaljik ve duygusal</option>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">Veya Konu Yaz (opsiyonel)</label>
                <input type="text" id="aiTopicInput" class="form-control" placeholder="Örn: Yeni ürünümüz çiğ köy peyniri">
                <div class="form-text">Ürün seçilmediyse konu yazısı kullanılır</div>
            </div>
            <div class="col-12">
                <button type="button" id="aiGenerateBtn" class="btn-teal" data-url="{{ route('admin.instagram-posts.generate-caption') }}">
                    <i class="bi bi-stars me-1"></i> <span data-label>AI ile Üret</span>
                </button>
                <span id="aiGenerateStatus" class="ms-2 text-muted small"></span>
            </div>
        </div>
    </div>
</div>
@endif
