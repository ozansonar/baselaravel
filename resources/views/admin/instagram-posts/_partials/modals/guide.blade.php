{{-- İçerik Rehberi Modal — Instagram resmi standartları --}}
<div class="modal fade" id="igGuideModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content ig-guide-modal">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-info-circle me-2"></i> Instagram İçerik Rehberi
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs ig-guide-tabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#guide-feed" type="button">
                            <i class="bi bi-image me-1"></i> Feed Post
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#guide-reels" type="button">
                            <i class="bi bi-camera-reels me-1"></i> Reels
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#guide-story" type="button">
                            <i class="bi bi-circle-fill me-1"></i> Story
                        </button>
                    </li>
                </ul>

                <div class="tab-content ig-guide-tab-content">

                    {{-- ════════ FEED POST ════════ --}}
                    <div class="tab-pane fade show active" id="guide-feed" role="tabpanel">
                        <h6 class="ig-guide-section-title">📷 Feed Post (Görsel + Carousel)</h6>
                        <p class="text-muted small mb-3">
                            Profilinin ana akışında ve grid görünümünde kalıcı olarak görünen post.
                            Carousel ile maksimum 10 görsel yan yana paylaşılabilir.
                        </p>

                        <div class="ig-guide-spec-table">
                            <div class="ig-guide-spec-row">
                                <span class="ig-guide-spec-label">Önerilen Boyutlar</span>
                                <span class="ig-guide-spec-value">
                                    <strong>1080×1080</strong> (kare 1:1)<br>
                                    <strong>1080×1350</strong> (dikey 4:5)<br>
                                    <strong>1080×566</strong> (yatay 1.91:1)
                                </span>
                            </div>
                            <div class="ig-guide-spec-row">
                                <span class="ig-guide-spec-label">Minimum Boyut</span>
                                <span class="ig-guide-spec-value">320×320 px</span>
                            </div>
                            <div class="ig-guide-spec-row">
                                <span class="ig-guide-spec-label">Maksimum Genişlik</span>
                                <span class="ig-guide-spec-value">1440 px</span>
                            </div>
                            <div class="ig-guide-spec-row">
                                <span class="ig-guide-spec-label">Aspect Ratio</span>
                                <span class="ig-guide-spec-value">
                                    <strong>4:5 (0.80) — 1.91:1 (1.91)</strong> arası<br>
                                    <small class="text-muted">Bu aralık dışı görseller reddedilir.</small>
                                </span>
                            </div>
                            <div class="ig-guide-spec-row">
                                <span class="ig-guide-spec-label">Format</span>
                                <span class="ig-guide-spec-value">JPG, PNG, WebP</span>
                            </div>
                            <div class="ig-guide-spec-row">
                                <span class="ig-guide-spec-label">Maksimum Boyut</span>
                                <span class="ig-guide-spec-value">8 MB / dosya</span>
                            </div>
                            <div class="ig-guide-spec-row">
                                <span class="ig-guide-spec-label">Carousel</span>
                                <span class="ig-guide-spec-value">1 ana + 9 ek = max 10 görsel</span>
                            </div>
                            <div class="ig-guide-spec-row">
                                <span class="ig-guide-spec-label">Caption</span>
                                <span class="ig-guide-spec-value">Max 2.000 karakter</span>
                            </div>
                            <div class="ig-guide-spec-row">
                                <span class="ig-guide-spec-label">Hashtag</span>
                                <span class="ig-guide-spec-value">Max 30 hashtag</span>
                            </div>
                            <div class="ig-guide-spec-row">
                                <span class="ig-guide-spec-label">Facebook Cross-Post</span>
                                <span class="ig-guide-spec-value">✓ Destekleniyor (form'da seçilebilir)</span>
                            </div>
                            <div class="ig-guide-spec-row">
                                <span class="ig-guide-spec-label">Kalıcılık</span>
                                <span class="ig-guide-spec-value">Kalıcı (silmediğin sürece)</span>
                            </div>
                        </div>

                        <div class="ig-guide-tip">
                            <i class="bi bi-lightbulb me-1"></i>
                            <strong>İpucu:</strong> Kare (1080×1080) en güvenli format —
                            grid görünümünde kırpılmadan görünür. Dikey (1080×1350) feed'de daha
                            çok yer kaplar, etkileşim oranı genelde daha yüksektir.
                        </div>
                    </div>

                    {{-- ════════ REELS ════════ --}}
                    <div class="tab-pane fade" id="guide-reels" role="tabpanel">
                        <h6 class="ig-guide-section-title">🎬 Reels (Kısa Video)</h6>
                        <p class="text-muted small mb-3">
                            Kısa dikey video formatı. Hem Reels tab'ında hem ana feed'de görünür,
                            kalıcıdır. TikTok benzeri içerik için ideal.
                        </p>

                        <div class="ig-guide-spec-table">
                            <div class="ig-guide-spec-row">
                                <span class="ig-guide-spec-label">Önerilen Boyut</span>
                                <span class="ig-guide-spec-value">
                                    <strong>1080×1920</strong> (9:16 dikey)
                                </span>
                            </div>
                            <div class="ig-guide-spec-row">
                                <span class="ig-guide-spec-label">Aspect Ratio</span>
                                <span class="ig-guide-spec-value">9:16 (zorunlu)</span>
                            </div>
                            <div class="ig-guide-spec-row">
                                <span class="ig-guide-spec-label">Süre</span>
                                <span class="ig-guide-spec-value">
                                    <strong>3 — 90 saniye</strong><br>
                                    <small class="text-muted">Aralık dışı reddedilir.</small>
                                </span>
                            </div>
                            <div class="ig-guide-spec-row">
                                <span class="ig-guide-spec-label">Format</span>
                                <span class="ig-guide-spec-value">MP4, MOV</span>
                            </div>
                            <div class="ig-guide-spec-row">
                                <span class="ig-guide-spec-label">Codec</span>
                                <span class="ig-guide-spec-value">H.264 (önerilen) + AAC ses</span>
                            </div>
                            <div class="ig-guide-spec-row">
                                <span class="ig-guide-spec-label">Frame Rate</span>
                                <span class="ig-guide-spec-value">23–60 FPS (30 önerilen)</span>
                            </div>
                            <div class="ig-guide-spec-row">
                                <span class="ig-guide-spec-label">Maksimum Boyut</span>
                                <span class="ig-guide-spec-value">100 MB</span>
                            </div>
                            <div class="ig-guide-spec-row">
                                <span class="ig-guide-spec-label">Caption</span>
                                <span class="ig-guide-spec-value">Max 2.200 karakter</span>
                            </div>
                            <div class="ig-guide-spec-row">
                                <span class="ig-guide-spec-label">AI Görsel Üretimi</span>
                                <span class="ig-guide-spec-value">❌ Desteklenmiyor (video AI yok)</span>
                            </div>
                            <div class="ig-guide-spec-row">
                                <span class="ig-guide-spec-label">Facebook Cross-Post</span>
                                <span class="ig-guide-spec-value">✓ Destekleniyor (FB Page Reels)</span>
                            </div>
                            <div class="ig-guide-spec-row">
                                <span class="ig-guide-spec-label">Kalıcılık</span>
                                <span class="ig-guide-spec-value">Kalıcı (silmediğin sürece)</span>
                            </div>
                        </div>

                        <div class="ig-guide-tip">
                            <i class="bi bi-lightbulb me-1"></i>
                            <strong>İpucu:</strong> İlk 3 saniye kritik — izleyiciyi yakalayan dinamik
                            açılış kullan. Ses açık olarak optimize et, ama subtitle/caption ekle (sessiz
                            izleyenler için). Vertical 9:16 dışındaki oranlar Meta tarafında kabul edilmez.
                        </div>
                    </div>

                    {{-- ════════ STORY ════════ --}}
                    <div class="tab-pane fade" id="guide-story" role="tabpanel">
                        <h6 class="ig-guide-section-title">⭕ Story (24 Saat)</h6>
                        <p class="text-muted small mb-3">
                            Hızlı paylaşım, 24 saat sonra Meta tarafından otomatik silinir.
                            Görsel veya video kabul eder. Kampanya, anlık ürün, etkinlik için ideal.
                        </p>

                        <div class="ig-guide-spec-table">
                            <div class="ig-guide-spec-row">
                                <span class="ig-guide-spec-label">Önerilen Boyut</span>
                                <span class="ig-guide-spec-value">
                                    <strong>1080×1920</strong> (9:16 dikey)
                                </span>
                            </div>
                            <div class="ig-guide-spec-row">
                                <span class="ig-guide-spec-label">Aspect Ratio</span>
                                <span class="ig-guide-spec-value">
                                    <strong>0.50 — 0.70</strong> arası (9:16 ≈ 0.5625)<br>
                                    <small class="text-muted">Aralık dışı reddedilir.</small>
                                </span>
                            </div>
                            <div class="ig-guide-spec-row">
                                <span class="ig-guide-spec-label">Görsel Format</span>
                                <span class="ig-guide-spec-value">JPG, PNG, WebP — max 8 MB</span>
                            </div>
                            <div class="ig-guide-spec-row">
                                <span class="ig-guide-spec-label">Video Format</span>
                                <span class="ig-guide-spec-value">MP4, MOV — max 100 MB</span>
                            </div>
                            <div class="ig-guide-spec-row">
                                <span class="ig-guide-spec-label">Video Süre</span>
                                <span class="ig-guide-spec-value">
                                    <strong>1 — 60 saniye</strong><br>
                                    <small class="text-muted">Daha uzun video parça parça yayınlanır.</small>
                                </span>
                            </div>
                            <div class="ig-guide-spec-row">
                                <span class="ig-guide-spec-label">Yaşam Süresi</span>
                                <span class="ig-guide-spec-value">24 saat (Meta otomatik siler)</span>
                            </div>
                            <div class="ig-guide-spec-row">
                                <span class="ig-guide-spec-label">Hem Görsel Hem Video?</span>
                                <span class="ig-guide-spec-value">İkisini birden yüklersen video önceliklidir.</span>
                            </div>
                            <div class="ig-guide-spec-row">
                                <span class="ig-guide-spec-label">Engagement Metrics</span>
                                <span class="ig-guide-spec-value">⚠ 24 saat sonra 404 (Meta sildi)</span>
                            </div>
                            <div class="ig-guide-spec-row">
                                <span class="ig-guide-spec-label">Facebook Cross-Post</span>
                                <span class="ig-guide-spec-value">❌ Yok (Page Story API yok — Meta kısıtı)</span>
                            </div>
                        </div>

                        <div class="ig-guide-tip">
                            <i class="bi bi-lightbulb me-1"></i>
                            <strong>İpucu:</strong> Story'ler 9:16 dışındaki oranlarda Meta otomatik kırpar
                            veya beyaz boşluk ekler. En iyi sonuç için kameraya dikey çek veya 1080×1920'a
                            crop et. Highlights'a eklenirse 24 saatten sonra da kalıcı görünür.
                        </div>

                        <div class="alert alert-info mt-3 small mb-0">
                            <i class="bi bi-info-circle me-1"></i>
                            <strong>Önemli:</strong> Story Facebook'ta paylaşılmaz — sadece Instagram'a gider.
                            Form'da Facebook checkbox'ı Story seçince otomatik kapanır.
                        </div>
                    </div>

                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-glass" data-bs-dismiss="modal">Anladım</button>
            </div>
        </div>
    </div>
</div>
