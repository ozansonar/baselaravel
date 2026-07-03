@php
    /** @var \App\Models\InstagramPost|null $post */
    /** @var bool $isEdit */
    /** @var bool $isPublished */
    /** @var bool $fbConfigured */
    /** @var string $scheduledAtValue */

    // Yeni post: FB entegrasyonu kuruluysa varsayılan açık. Edit: post tercihini koru.
    $defaultFb   = $isEdit ? ($post->publish_to_facebook ? '1' : '0') : ($fbConfigured ? '1' : '0');
    $publishToFb = old('publish_to_facebook', $defaultFb);
@endphp

<div class="card-dark mb-4">
    <div class="card-header-custom">
        <div class="form-section-header mb-0">
            <div class="form-section-icon bg-icon-blue"><i class="bi bi-calendar-event"></i></div>
            <div>
                <h6 class="mb-0">Yayın Planı</h6>
                <small class="text-muted">Ne zaman yayınlansın?</small>
            </div>
        </div>
    </div>
    <div class="card-body-custom">

        {{-- Yayın hedefi seçimi --}}
        <div class="mb-3">
            <label class="form-label">Nereye Paylaşılacak?</label>

            <div class="ig-target-checks">
                <label class="ig-target-check ig-target-check--ig">
                    <input type="checkbox" checked disabled>
                    <span class="ig-target-icon"><i class="bi bi-instagram"></i></span>
                    <span class="ig-target-info">
                        <strong>Instagram</strong>
                        <small>Her zaman paylaşılır</small>
                    </span>
                </label>

                <label class="ig-target-check ig-target-check--fb {{ ! $fbConfigured ? 'ig-target-disabled' : '' }}"
                       data-ig-fb-toggle>
                    <input type="hidden" name="publish_to_facebook" value="0">
                    <input type="checkbox" name="publish_to_facebook" value="1"
                           {{ $publishToFb === '1' ? 'checked' : '' }}
                           {{ ! $fbConfigured || $isPublished ? 'disabled' : '' }}>
                    <span class="ig-target-icon"><i class="bi bi-facebook"></i></span>
                    <span class="ig-target-info">
                        <strong>Facebook Sayfası</strong>
                        <small data-ig-fb-hint>
                            @if($fbConfigured)
                                Aynı içerik Facebook'ta da paylaşılacak
                            @else
                                <span class="text-warning">Page Token kurulu değil — <a href="{{ route('admin.settings.index') }}#stg-instagram">Ayarlar</a></span>
                            @endif
                        </small>
                    </span>
                </label>

                {{-- TikTok cross-post — docs/tiktok.md Bölüm 6.1 --}}
                @php
                    $ttConfigured = isset($ttConfigured) ? $ttConfigured
                        : (\App\Models\Setting::getValue('tiktok_enabled', '0') === '1'
                            && trim((string) \App\Models\Setting::getValue('tiktok_access_token', '')) !== '');
                    $ttPostMode = isset($ttPostMode) ? $ttPostMode
                        : (string) \App\Models\Setting::getValue('tiktok_post_mode', 'inbox');
                    $publishToTt = isset($publishToTt) ? $publishToTt
                        : (old('publish_to_tiktok',
                            $isEdit ? ($post->publish_to_tiktok ? '1' : '0')
                                    : ($ttConfigured ? '1' : '0')));
                @endphp
                <label class="ig-target-check ig-target-check--tt {{ ! $ttConfigured ? 'ig-target-disabled' : '' }}"
                       data-ig-tt-toggle>
                    <input type="hidden" name="publish_to_tiktok" value="0">
                    <input type="checkbox" name="publish_to_tiktok" value="1"
                           {{ $publishToTt === '1' ? 'checked' : '' }}
                           {{ ! $ttConfigured || $isPublished ? 'disabled' : '' }}>
                    <span class="ig-target-icon"><i class="bi bi-tiktok"></i></span>
                    <span class="ig-target-info">
                        <strong>TikTok</strong>
                        <small data-ig-tt-hint>
                            @if($ttConfigured)
                                @if($ttPostMode === 'inbox')
                                    Inbox'a düşer (mobilde yayınla)
                                @else
                                    Otomatik yayınlanır (Direct Post)
                                @endif
                            @else
                                <span class="text-warning">Bağlantı kurulu değil — <a href="{{ route('admin.settings.index') }}#stg-tiktok">Ayarlar</a></span>
                            @endif
                        </small>
                    </span>
                </label>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label" for="scheduledAt">Planlanan Yayın Tarihi</label>
            <input type="datetime-local" name="scheduled_at" id="scheduledAt"
                   class="form-control @error('scheduled_at') is-invalid @enderror"
                   value="{{ $scheduledAtValue }}"
                   min="{{ now()->format('Y-m-d\TH:i') }}"
                   {{ $isPublished ? 'disabled' : '' }}>
            <div class="form-text">Boş bırakılırsa taslak olarak kaydedilir. Geçmiş tarih kabul edilmez. Dolduğunda "Planla" butonu ile planlanır.</div>
            @error('scheduled_at')
            <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        @if($isEdit)
        <div class="mb-3">
            <label class="form-label">Mevcut Durum</label>
            <div>
                <span class="usr-status-badge {{ $post->status->badgeClass() }}">
                    <i class="bi {{ $post->status->icon() }} me-1"></i>{{ $post->status->label() }}
                </span>
            </div>
        </div>

        @if($post->published_at)
        <div class="mb-3 small">
            <strong>Yayın Tarihi:</strong> {{ $post->published_at->format('d.m.Y H:i') }}
        </div>
        @endif

        @if($post->permalink)
        <div class="mb-3">
            <a href="{{ $post->permalink }}" target="_blank" rel="noopener" class="btn-glass w-100">
                <i class="bi bi-instagram me-1"></i> Instagram'da Gör
            </a>
        </div>
        @endif

        @if($post->error_message)
        <div class="mb-3">
            <label class="form-label text-danger">Son Hata</label>
            <div class="small text-danger">{{ $post->error_message }}</div>
        </div>
        @endif

        @if($post->retry_count > 0)
        <div class="mb-3 small text-muted">
            <i class="bi bi-arrow-repeat me-1"></i> Deneme sayısı: {{ $post->retry_count }}
        </div>
        @endif

        {{-- Kurtarma aksiyonları — sadece Failed/Scheduled/Draft için anlamlı --}}
        @if($post->status !== \App\Enums\InstagramPostStatus::Published)
            @php
                $isRetryExhausted = $post->status === \App\Enums\InstagramPostStatus::Failed
                    && $post->retry_count >= \App\Models\InstagramPost::MAX_RETRY_COUNT;
            @endphp

            <div class="d-grid gap-2 mt-3">
                {{-- Şimdi Yayınla --}}
                <form method="POST" action="{{ route('admin.instagram-posts.publish-now', $post->id) }}"
                      class="ig-publish-now-form">
                    @csrf
                    <button type="submit" class="btn-teal w-100">
                        <i class="bi bi-send-fill me-1"></i> Şimdi Yayınla
                    </button>
                </form>

                {{-- Retry sayacını sıfırla — sadece kalıcı hatada görünür --}}
                @if($isRetryExhausted)
                    <form method="POST" action="{{ route('admin.instagram-posts.reset-retry', $post->id) }}"
                          class="ig-reset-retry-form">
                        @csrf
                        <button type="submit" class="btn-glass w-100 text-warning">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>
                            Retry Sayacını Sıfırla (Cron yeniden denesin)
                        </button>
                    </form>
                    <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        Sıfırlayınca status "Planlandı"ya döner. Önce caption/hashtag uzunluğunu
                        kontrol et (Instagram 2200 char limiti) — bu fix sonrası otomatik kırpılıyor
                        ama eski hatalı içerik aynen kalmış olabilir.
                    </small>
                @endif
            </div>
        @endif

        {{-- TikTok durum bilgisi + recovery — docs/tiktok.md Bölüm 6.3 --}}
        @if ($post->publish_to_tiktok)
            <hr class="my-3">
            <h6 class="mb-2 small text-clr-secondary">
                <i class="bi bi-tiktok me-1"></i> TIKTOK DURUMU
            </h6>

            @if ($post->isTikTokPublished())
                <div class="alert alert-success small mb-0 d-flex align-items-center gap-2">
                    <i class="bi bi-check-circle-fill"></i>
                    <span class="flex-grow-1">
                        <strong>TikTok'ta yayında</strong>
                        @if ($post->tt_published_at)
                            <small class="text-muted">— {{ $post->tt_published_at->translatedFormat('d M Y H:i') }}</small>
                        @endif
                    </span>
                    @if ($post->tt_permalink)
                        <a href="{{ $post->tt_permalink }}" target="_blank" rel="noopener" class="btn-glass btn-glass-sm">
                            <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                    @endif
                </div>
            @elseif ($post->isTikTokFailed())
                <div class="alert alert-danger small mb-2">
                    <i class="bi bi-x-octagon-fill me-1"></i>
                    <strong>Kalıcı TikTok hatası</strong>
                    @if ($post->tt_error_message)
                        <div class="mt-1 text-break">{{ $post->tt_error_message }}</div>
                    @endif
                </div>
                <div class="d-grid gap-2">
                    <form method="POST" action="{{ route('admin.instagram-posts.tt-publish-now', $post->id) }}" class="ig-tt-publish-now-form">
                        @csrf
                        <button type="submit" class="btn-teal w-100">
                            <i class="bi bi-tiktok me-1"></i> Şimdi TikTok'a Paylaş
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.instagram-posts.tt-reset-retry', $post->id) }}" class="ig-tt-reset-retry-form">
                        @csrf
                        <button type="submit" class="btn-glass w-100 text-warning">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>
                            TikTok Retry Sayacını Sıfırla
                        </button>
                    </form>
                </div>
            @elseif ($post->tt_inbox_id !== null)
                <div class="alert alert-warning small mb-0">
                    <i class="bi bi-inbox-fill me-1"></i>
                    <strong>TikTok Inbox'a düştü.</strong> Mobilde TikTok aç →
                    Inbox → Drafts → "Paylaş" tıkla.
                </div>
            @elseif ($post->status === \App\Enums\InstagramPostStatus::Published)
                <div class="d-grid">
                    <form method="POST" action="{{ route('admin.instagram-posts.tt-publish-now', $post->id) }}" class="ig-tt-publish-now-form">
                        @csrf
                        <button type="submit" class="btn-glass w-100">
                            <i class="bi bi-tiktok me-1"></i> Şimdi TikTok'a Dene
                        </button>
                    </form>
                    <small class="text-muted mt-1">
                        Cron 5dk içinde otomatik denenir. Manuel tetikleme isteğe bağlı.
                        @if ($post->tt_retry_count > 0)
                            <br>Deneme: {{ $post->tt_retry_count }}/{{ \App\Models\InstagramPost::MAX_RETRY_COUNT }}
                        @endif
                    </small>
                </div>
            @endif

            @if ($post->tt_error_message && ! $post->isTikTokFailed())
                <div class="small text-muted mt-2">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Son hata: {{ $post->tt_error_message }}
                </div>
            @endif
        @endif

        @endif

    </div>
</div>
