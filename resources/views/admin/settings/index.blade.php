@extends('layouts.admin')

@section('title', 'Proje Ayarları')
@section('page_title', 'Proje Ayarları')
@section('page_description', 'Uygulama yapılandırması, iletişim bilgileri, SEO, entegrasyonlar ve sistem tercihleri')

@php
    $s = fn(string $key, ?string $default = null): ?string => ($settings[$key] ?? null)?->value ?? $default;
@endphp

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/glightbox@3.3.0/dist/css/glightbox.min.css">
<style>
.stg-google-preview{background:var(--admin-card-bg, #1a1d23);border:1px solid var(--admin-border, rgba(255,255,255,.08));border-radius:12px;padding:20px}
.stg-google-preview-inner{background:var(--admin-sidebar-bg, #13151a);border:1px solid var(--admin-border, rgba(255,255,255,.06));border-radius:10px;padding:16px 20px}
.stg-google-url{display:block;font-size:13px;font-style:normal;color:#8ab4f8;margin-bottom:4px;line-height:1.4}
.stg-google-title{font-size:18px;font-weight:400;color:#99c3ff;margin:0 0 6px;line-height:1.3;cursor:pointer}
.stg-google-title:hover{text-decoration:underline}
.stg-google-desc{font-size:13px;color:var(--admin-text-muted, #9ca3af);margin:0;line-height:1.5}

/* Color picker field */
.stg-color-field{display:flex;align-items:center;gap:10px}
.stg-color-picker{width:44px;height:44px;padding:2px;border:1px solid var(--admin-border, rgba(255,255,255,.1));border-radius:8px;cursor:pointer;background:var(--admin-card-bg, #1a1d23);flex-shrink:0}
.stg-color-picker::-webkit-color-swatch-wrapper{padding:2px}
.stg-color-picker::-webkit-color-swatch{border-radius:4px;border:none}
.stg-color-picker::-moz-color-swatch{border-radius:4px;border:none}
.stg-color-hex{max-width:100px;font-family:monospace;font-size:13px;text-transform:uppercase;letter-spacing:.5px}
.stg-switch-row{display:flex;align-items:center;gap:12px}
.stg-switch-text{font-size:14px;color:var(--admin-text, #e2e8f0)}

/* Mail theme preview */
.stg-mail-preview-wrap{background:var(--admin-card-bg, #1a1d23);border:1px solid var(--admin-border, rgba(255,255,255,.08));border-radius:12px;padding:24px;overflow:hidden}
.stg-mail-preview{max-width:460px;margin:0 auto;border-radius:12px;overflow:hidden;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;box-shadow:0 4px 24px rgba(0,0,0,.3)}
.stg-mp-header{padding:24px 30px;text-align:center;border-radius:12px 12px 0 0}
.stg-mp-logo{font-size:18px;font-weight:800;color:#fff}
.stg-mp-logo-img{max-height:48px;width:auto;display:block;margin:0 auto}
.stg-mp-accent{height:3px;background:linear-gradient(90deg,#d4a84b,#7cb342,#d4a84b)}
.stg-mp-content{padding:30px}
.stg-mp-greeting{font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:1.5px;margin:0 0 6px}
.stg-mp-heading{font-size:20px;font-weight:800;margin:0 0 14px;line-height:1.3}
.stg-mp-text{font-size:13px;line-height:1.7;margin:0 0 16px}
.stg-mp-muted{font-size:11px;margin:16px 0 0;line-height:1.6}
.stg-mp-btn-wrap{text-align:center;padding:12px 0}
.stg-mp-btn{display:inline-block;padding:10px 28px;color:#fff !important;border-radius:6px;font-weight:700;font-size:13px;cursor:default}
.stg-mp-footer{padding:24px 30px;text-align:center;border-radius:0 0 12px 12px}
.stg-mp-footer-link{font-size:13px;color:#c5e1a5;margin:0 0 4px;font-weight:600}
.stg-mp-footer-text{font-size:12px;color:#a5d6a7;margin:4px 0;line-height:1.5}
.stg-mp-footer-copy{font-size:10px;color:#81c784;margin:12px 0 0}
.stg-mp-social{display:flex;justify-content:center;gap:10px;margin:12px 0}
.stg-mp-social-icon{width:30px;height:30px;border-radius:50%;background:rgba(255,255,255,.15);display:inline-flex;align-items:center;justify-content:center;font-size:14px;color:#fff}

/* System status - styles.css'deki kuralları tamamlar */

/* Danger zone */
.stg-danger-zone{border:1px solid rgba(239,68,68,.25);border-radius:12px;padding:20px;margin-top:8px}
.stg-danger-item{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:12px 0}
.stg-danger-info{display:flex;flex-direction:column;gap:2px}
.stg-danger-info strong{font-size:15px;color:var(--admin-text, #e2e8f0)}
.stg-danger-info small{font-size:13px;color:var(--admin-text-muted, #9ca3af)}
.stg-btn-danger{display:inline-flex;align-items:center;gap:6px;padding:8px 20px;background:transparent;border:1px solid rgba(239,68,68,.4);color:#ef4444;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;transition:all .2s;white-space:nowrap}
.stg-btn-danger:hover{background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.6)}
</style>
@endpush

@section('content')

{{-- Page Header --}}
<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h2>Proje Ayarları</h2>
        <p>Uygulama yapılandırması, iletişim bilgileri, SEO, entegrasyonlar ve sistem tercihleri</p>
    </div>
</div>

{{-- Settings Layout --}}
<div class="stg-layout">

    {{-- Settings Nav --}}
    <div class="stg-nav">
        <div class="stg-nav-inner">
            <a href="#stg-general" class="stg-nav-item active" onclick="switchSettingsTab(this,'stg-general')">
                <i class="bi bi-sliders2"></i>
                <div><span>Genel</span><small>Proje bilgileri & temel ayarlar</small></div>
            </a>
            <a href="#stg-contact" class="stg-nav-item" onclick="switchSettingsTab(this,'stg-contact')">
                <i class="bi bi-telephone"></i>
                <div><span>İletişim & Adres</span><small>Telefon, e-posta, adres bilgileri</small></div>
            </a>
            <a href="#stg-social" class="stg-nav-item" onclick="switchSettingsTab(this,'stg-social')">
                <i class="bi bi-share"></i>
                <div><span>Sosyal Medya</span><small>Facebook, Instagram, YouTube</small></div>
            </a>
            <a href="#stg-seo" class="stg-nav-item" onclick="switchSettingsTab(this,'stg-seo')">
                <i class="bi bi-search"></i>
                <div><span>SEO & Meta</span><small>Title, description, keywords</small></div>
            </a>
            <a href="#stg-shipping" class="stg-nav-item" onclick="switchSettingsTab(this,'stg-shipping')">
                <i class="bi bi-truck"></i>
                <div><span>E-ticaret & Kargo</span><small>Kargo ücreti, minimum sipariş</small></div>
            </a>
            <a href="#stg-appearance" class="stg-nav-item" onclick="switchSettingsTab(this,'stg-appearance')">
                <i class="bi bi-palette"></i>
                <div><span>Görünüm</span><small>OG görseli, bakım modu</small></div>
            </a>
            <a href="#stg-email" class="stg-nav-item" onclick="switchSettingsTab(this,'stg-email')">
                <i class="bi bi-envelope-at"></i>
                <div><span>E-posta (SMTP)</span><small>Giden e-posta sunucu ayarları</small></div>
            </a>
            <a href="#stg-mail-theme" class="stg-nav-item" onclick="switchSettingsTab(this,'stg-mail-theme')">
                <i class="bi bi-palette2"></i>
                <div><span>Mail Teması</span><small>Renk, footer & sosyal medya</small></div>
            </a>
            <a href="#stg-recaptcha" class="stg-nav-item" onclick="switchSettingsTab(this,'stg-recaptcha')">
                <i class="bi bi-shield-check"></i>
                <div><span>reCAPTCHA</span><small>Google reCAPTCHA v2 doğrulama</small></div>
            </a>
            <a href="#stg-google-maps" class="stg-nav-item" onclick="switchSettingsTab(this,'stg-google-maps')">
                <i class="bi bi-geo-alt"></i>
                <div><span>Google Haritalar</span><small>Google Places API & yorumlar</small></div>
            </a>
            <a href="#stg-youtube" class="stg-nav-item" onclick="switchSettingsTab(this,'stg-youtube')">
                <i class="bi bi-youtube"></i>
                <div><span>YouTube</span><small>YouTube Data API & videolar</small></div>
            </a>
            <a href="#stg-instagram" class="stg-nav-item" onclick="switchSettingsTab(this,'stg-instagram')">
                <i class="bi bi-instagram"></i>
                <div><span>Instagram</span><small>Graph API & otomatik paylaşım</small></div>
            </a>
            <a href="#stg-tiktok" class="stg-nav-item" onclick="switchSettingsTab(this,'stg-tiktok')">
                <i class="bi bi-tiktok"></i>
                <div><span>TikTok</span><small>Cross-post Photo Mode / Video</small></div>
            </a>
            <a href="#stg-telegram" class="stg-nav-item" onclick="switchSettingsTab(this,'stg-telegram')">
                <i class="bi bi-telegram"></i>
                <div><span>Telegram</span><small>Hata bildirimleri</small></div>
            </a>
            <a href="#stg-ai" class="stg-nav-item" onclick="switchSettingsTab(this,'stg-ai')">
                <i class="bi bi-robot"></i>
                <div><span>AI İçerik</span><small>Gemini API ile otomatik blog</small></div>
            </a>
            <a href="#stg-regional" class="stg-nav-item" onclick="switchSettingsTab(this,'stg-regional')">
                <i class="bi bi-globe2"></i>
                <div><span>Bölgesel</span><small>Dil ve saat dilimi tercihleri</small></div>
            </a>
            <a href="#stg-system" class="stg-nav-item" onclick="switchSettingsTab(this,'stg-system')">
                <i class="bi bi-gear"></i>
                <div><span>Sistem</span><small>Sistem durumu & tehlikeli bölge</small></div>
            </a>
        </div>
    </div>

    {{-- Settings Content --}}
    <div class="stg-content">

        {{-- ══════════════ 1. GENEL AYARLAR ══════════════ --}}
        <div class="stg-panel active" id="stg-general">
            <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="stg-panel-header">
                    <div>
                        <h5><i class="bi bi-sliders2"></i> Genel Ayarlar</h5>
                        <p>Projenizin temel bilgilerini buradan yönetin</p>
                    </div>
                    <button type="submit" class="stg-save-btn"><i class="bi bi-check-lg"></i> Kaydet</button>
                </div>

                {{-- Proje Bilgileri --}}
                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>Proje Bilgileri</h6>
                        <p>Temel proje adı ve açıklama ayarları</p>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Proje Adı</label>
                        <input type="text" class="stg-input" name="settings[site_name]"
                               value="{{ $s('site_name', config('app.name')) }}" placeholder="Projenizin adını girin">
                        <small class="stg-hint">Proje genelinde kullanılacak ana isim (navbar, footer, e-postalar, SEO vb.)</small>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Site Başlığı</label>
                        <input type="text" class="stg-input" name="settings[site_title]"
                               value="{{ $s('site_title') }}" placeholder="Site başlığını girin">
                        <small class="stg-hint">Tarayıcı sekmesinde ve başlık çubuğunda görüntülenir</small>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Site Açıklaması</label>
                        <textarea class="stg-textarea" name="settings[site_description]" rows="3"
                                  placeholder="Kısa bir açıklama yazın">{{ $s('site_description') }}</textarea>
                        <small class="stg-hint">Ana sayfada ve meta description'da kullanılır</small>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Site Anahtar Kelimeleri</label>
                        <textarea class="stg-textarea" name="settings[site_keywords]" rows="2"
                                  placeholder="virgülle ayırarak yazın">{{ $s('site_keywords') }}</textarea>
                        <small class="stg-hint">SEO için meta keywords. Virgülle ayırarak yazın</small>
                    </div>
                </div>

                {{-- Proje Logosu --}}
                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>Proje Logosu & Favicon</h6>
                        <p>Site genelinde kullanılacak logo ve favicon görselleri</p>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Logo</label>
                        <div class="stg-logo-upload">
                            <div class="stg-logo-preview">
                                @if($s('site_logo'))
                                <a href="{{ upload_url($s('site_logo')) }}" class="glightbox" data-gallery="settings" data-title="Site Logosu">
                                    <img class="stg-logo-img" id="logoPreviewImg" src="{{ upload_url($s('site_logo')) }}" alt="Logo">
                                </a>
                                @else
                                <div class="stg-logo-current" id="logoDefault">O</div>
                                <img class="stg-logo-img d-none" id="logoPreviewImg" src="" alt="Logo">
                                @endif
                            </div>
                            <div class="stg-logo-actions">
                                <input type="file" id="logoFileInput" name="files[site_logo]" accept="image/png,image/jpeg,image/svg+xml,image/webp" hidden>
                                <button type="button" class="stg-btn stg-btn-sm" onclick="document.getElementById('logoFileInput').click()">
                                    <i class="bi bi-upload"></i> Logo Yükle
                                </button>
                                <small class="text-muted">PNG, JPG, SVG veya WebP. Maks. 1 MB. Önerilen: 400×400px</small>
                            </div>
                        </div>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Favicon</label>
                        <div class="stg-logo-upload">
                            <div class="stg-logo-preview">
                                @if($s('site_favicon'))
                                <a href="{{ upload_url($s('site_favicon')) }}" class="glightbox" data-gallery="settings" data-title="Favicon">
                                    <img class="stg-logo-img" id="faviconPreviewImg" src="{{ upload_url($s('site_favicon')) }}" alt="Favicon">
                                </a>
                                @else
                                <div class="stg-logo-current" id="faviconDefault"><i class="bi bi-star-fill"></i></div>
                                <img class="stg-logo-img d-none" id="faviconPreviewImg" src="" alt="Favicon">
                                @endif
                            </div>
                            <div class="stg-logo-actions">
                                <input type="file" id="faviconFileInput" name="files[site_favicon]" accept="image/png,image/x-icon,image/svg+xml" hidden>
                                <button type="button" class="stg-btn stg-btn-sm" onclick="document.getElementById('faviconFileInput').click()">
                                    <i class="bi bi-upload"></i> Favicon Yükle
                                </button>
                                <small class="text-muted">ICO, PNG veya SVG. Maks. 1 MB. Önerilen: 64×64px</small>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>Footer Bilgileri</h6>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Footer Metni</label>
                        <input type="text" class="stg-input" name="settings[footer_text]"
                               value="{{ $s('footer_text') }}" placeholder="Footer metin bilgisi">
                        <small class="stg-hint">Site alt kısmında görünecek telif hakkı metni</small>
                    </div>
                </div>
            </form>
        </div>

        {{-- ══════════════ 2. İLETİŞİM & ADRES ══════════════ --}}
        <div class="stg-panel" id="stg-contact">
            <form method="POST" action="{{ route('admin.settings.update') }}">
                @csrf
                @method('PUT')

                <div class="stg-panel-header">
                    <div>
                        <h5><i class="bi bi-telephone"></i> İletişim & Adres</h5>
                        <p>Telefon, e-posta ve adres bilgilerinizi yönetin</p>
                    </div>
                    <button type="submit" class="stg-save-btn"><i class="bi bi-check-lg"></i> Kaydet</button>
                </div>

                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>İletişim Bilgileri</h6>
                        <p>Müşterilerin size ulaşabileceği bilgiler</p>
                    </div>

                    <div class="stg-row">
                        <div class="stg-field stg-half">
                            <label class="stg-label">Telefon (Birincil)</label>
                            <input type="text" class="stg-input" name="settings[contact_phone]"
                                   value="{{ $s('contact_phone') }}" placeholder="+90 555 123 45 67">
                        </div>
                        <div class="stg-field stg-half">
                            <label class="stg-label">Telefon (İkincil)</label>
                            <input type="text" class="stg-input" name="settings[contact_phone_2]"
                                   value="{{ $s('contact_phone_2') }}" placeholder="+90 555 987 65 43">
                        </div>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">E-posta Adresi</label>
                        <input type="email" class="stg-input" name="settings[contact_email]"
                               value="{{ $s('contact_email') }}" placeholder="iletisim@domain.com">
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Yönetici Bildirim E-postası</label>
                        <input type="email" class="stg-input" name="settings[admin_notification_email]"
                               value="{{ $s('admin_notification_email') }}" placeholder="bildirim@domain.com">
                        <small class="stg-help-text">Yeni sipariş bildirimleri ve Instagram kalıcı hata uyarıları bu adrese gönderilir. Boşsa "İletişim E-posta Adresi" kullanılır.</small>
                    </div>
                </div>

                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>Adres Bilgileri</h6>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Açık Adres</label>
                        <textarea class="stg-textarea" name="settings[contact_address]" rows="3"
                                  placeholder="Tam adres bilgisi">{{ $s('contact_address') }}</textarea>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Google Maps Embed Kodu</label>
                        <textarea class="stg-textarea" name="settings[contact_map_embed]" rows="4"
                                  placeholder="<iframe src='...'></iframe> veya Google Maps linki">{{ $s('contact_map_embed') }}</textarea>
                        <small class="stg-hint">Google Maps'ten alacağınız embed iframe kodunu veya linki yapıştırın</small>
                    </div>
                </div>

                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>Çalışma Saatleri</h6>
                    </div>

                    <div class="stg-row">
                        <div class="stg-field stg-half">
                            <label class="stg-label">Hafta İçi</label>
                            <input type="text" class="stg-input" name="settings[working_hours_weekday]"
                                   value="{{ $s('working_hours_weekday', '08:00 - 18:00') }}" placeholder="08:00 - 18:00">
                        </div>
                        <div class="stg-field stg-half">
                            <label class="stg-label">Cumartesi</label>
                            <input type="text" class="stg-input" name="settings[working_hours_saturday]"
                                   value="{{ $s('working_hours_saturday', '09:00 - 16:00') }}" placeholder="09:00 - 16:00">
                        </div>
                    </div>
                    <div class="stg-field">
                        <label class="stg-label">Pazar</label>
                        <input type="text" class="stg-input" name="settings[working_hours_sunday]"
                               value="{{ $s('working_hours_sunday', 'Kapalı') }}" placeholder="Kapalı">
                    </div>
                </div>
            </form>
        </div>

        {{-- ══════════════ 3. SOSYAL MEDYA ══════════════ --}}
        <div class="stg-panel" id="stg-social">
            <form method="POST" action="{{ route('admin.settings.update') }}">
                @csrf
                @method('PUT')

                <div class="stg-panel-header">
                    <div>
                        <h5><i class="bi bi-share"></i> Sosyal Medya</h5>
                        <p>Sosyal medya hesap bağlantılarınız</p>
                    </div>
                    <button type="submit" class="stg-save-btn"><i class="bi bi-check-lg"></i> Kaydet</button>
                </div>

                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>Sosyal Medya Hesapları</h6>
                        <p>Bağlantıları doldurun, boş bırakılanlar sitede gösterilmez</p>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label"><i class="bi bi-facebook me-2 text-primary"></i>Facebook</label>
                        <input type="url" class="stg-input" name="settings[social_facebook]"
                               value="{{ $s('social_facebook') }}" placeholder="https://facebook.com/sayfaniz">
                    </div>

                    <div class="stg-field">
                        <label class="stg-label"><i class="bi bi-instagram me-2 text-instagram"></i>Instagram</label>
                        <input type="url" class="stg-input" name="settings[social_instagram]"
                               value="{{ $s('social_instagram') }}" placeholder="https://instagram.com/sayfaniz">
                    </div>

                    <div class="stg-field">
                        <label class="stg-label"><i class="bi bi-twitter-x me-2"></i>X (Twitter)</label>
                        <input type="url" class="stg-input" name="settings[social_twitter]"
                               value="{{ $s('social_twitter') }}" placeholder="https://x.com/sayfaniz">
                    </div>

                    <div class="stg-field">
                        <label class="stg-label"><i class="bi bi-youtube me-2 text-danger"></i>YouTube</label>
                        <input type="url" class="stg-input" name="settings[social_youtube]"
                               value="{{ $s('social_youtube') }}" placeholder="https://youtube.com/@kanaliniz">
                    </div>

                    <div class="stg-field">
                        <label class="stg-label"><i class="bi bi-whatsapp me-2 text-success"></i>WhatsApp</label>
                        <input type="text" class="stg-input" name="settings[social_whatsapp]"
                               value="{{ $s('social_whatsapp') }}" placeholder="https://wa.me/905551234567 veya telefon numarası">
                        <small class="stg-hint">WhatsApp linki veya telefon numarası (ör: +905051234567)</small>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label"><i class="bi bi-tiktok me-2"></i>TikTok</label>
                        <input type="url" class="stg-input" name="settings[social_tiktok]"
                               value="{{ $s('social_tiktok') }}" placeholder="https://tiktok.com/@sayfaniz">
                    </div>
                </div>
            </form>
        </div>

        {{-- ══════════════ 4. SEO & META ══════════════ --}}
        <div class="stg-panel" id="stg-seo">
            <form method="POST" action="{{ route('admin.settings.update') }}">
                @csrf
                @method('PUT')

                <div class="stg-panel-header">
                    <div>
                        <h5><i class="bi bi-search"></i> SEO & Meta Ayarları</h5>
                        <p>Arama motoru optimizasyonu ve meta etiket ayarları</p>
                    </div>
                    <button type="submit" class="stg-save-btn"><i class="bi bi-check-lg"></i> Kaydet</button>
                </div>

                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>Ana Sayfa SEO</h6>
                        <p>Ana sayfa için özel SEO meta etiketleri</p>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Ana Sayfa Başlığı (Title)</label>
                        <input type="text" class="stg-input" name="settings[seo_home_title]"
                               value="{{ $s('seo_home_title') }}" placeholder="Doğal Köy Ürünleri | {{ $s('site_name', config('app.name')) }}">
                        <small class="stg-hint">60 karakter altında tutmanız önerilir</small>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Ana Sayfa Açıklaması (Meta Description)</label>
                        <textarea class="stg-textarea" name="settings[seo_home_description]" rows="3"
                                  placeholder="Arama sonuçlarında görünecek açıklama">{{ $s('seo_home_description') }}</textarea>
                        <small class="stg-hint">155 karakter altında tutmanız önerilir</small>
                    </div>

                    {{-- Google Önizleme --}}
                    <div class="stg-field">
                        <label class="stg-label">Google Önizleme</label>
                        <div class="stg-google-preview">
                            <div class="stg-google-preview-inner">
                                <cite class="stg-google-url" id="seoPreviewUrl">{{ config('app.url') }}/</cite>
                                <h3 class="stg-google-title" id="seoPreviewTitle">{{ $s('seo_home_title') ?: config('app.name', 'Site Başlığı') }}</h3>
                                <p class="stg-google-desc" id="seoPreviewDesc">{{ $s('seo_home_description') ?: 'Arama sonuçlarında görünecek açıklama buraya gelecek.' }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>Analitik & Takip Kodları</h6>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Google Analytics ID</label>
                        <div class="stg-input-group">
                            <span class="stg-input-prefix">GA</span>
                            <input type="text" class="stg-input" name="settings[google_analytics_id]"
                                   value="{{ $s('google_analytics_id') }}" placeholder="G-XXXXXXXXXX">
                        </div>
                        <small class="stg-hint">Google Analytics 4 ölçüm kimliği</small>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Google Tag Manager ID</label>
                        <div class="stg-input-group">
                            <span class="stg-input-prefix">GTM</span>
                            <input type="text" class="stg-input" name="settings[google_tag_manager_id]"
                                   value="{{ $s('google_tag_manager_id') }}" placeholder="GTM-XXXXXXX">
                        </div>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Facebook Pixel ID</label>
                        <input type="text" class="stg-input" name="settings[facebook_pixel_id]"
                               value="{{ $s('facebook_pixel_id') }}" placeholder="XXXXXXXXXXXXXXX">
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Özel Head Kodu</label>
                        <textarea class="stg-textarea font-mono" name="settings[custom_head_code]" rows="4"
                                  placeholder="<script> veya <meta> etiketleri">{{ $s('custom_head_code') }}</textarea>
                        <small class="stg-hint">&lt;head&gt; etiketinin sonuna eklenecek özel kod (Google doğrulama, analitik vb.)</small>
                    </div>
                </div>

                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>Open Graph / Sosyal Paylaşım</h6>
                        <p>Sosyal medyada paylaşıldığında görünecek bilgiler</p>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">OG Başlık</label>
                        <input type="text" class="stg-input" name="settings[og_title]"
                               value="{{ $s('og_title') }}" placeholder="{{ $s('site_name', config('app.name')) }} - Doğal Köy Ürünleri">
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">OG Açıklama</label>
                        <textarea class="stg-textarea" name="settings[og_description]" rows="2"
                                  placeholder="Sosyal medya paylaşımlarında görünecek açıklama">{{ $s('og_description') }}</textarea>
                    </div>
                </div>
            </form>
        </div>

        {{-- ══════════════ 5. E-TİCARET & KARGO ══════════════ --}}
        <div class="stg-panel" id="stg-shipping">
            <form method="POST" action="{{ route('admin.settings.update') }}">
                @csrf
                @method('PUT')

                <div class="stg-panel-header">
                    <div>
                        <h5><i class="bi bi-truck"></i> E-ticaret & Kargo</h5>
                        <p>Kargo ücretleri, minimum sipariş tutarı ve teslimat ayarları</p>
                    </div>
                    <button type="submit" class="stg-save-btn"><i class="bi bi-check-lg"></i> Kaydet</button>
                </div>

                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>Kargo Ayarları</h6>
                    </div>

                    <div class="stg-row">
                        <div class="stg-field stg-half">
                            <label class="stg-label">Kargo Ücreti (₺)</label>
                            <div class="stg-input-group">
                                <span class="stg-input-prefix">₺</span>
                                <input type="text" class="stg-input" name="settings[shipping_fee]"
                                       value="{{ $s('shipping_fee', '39.90') }}" placeholder="39.90">
                            </div>
                        </div>
                        <div class="stg-field stg-half">
                            <label class="stg-label">Ücretsiz Kargo Limiti (₺)</label>
                            <div class="stg-input-group">
                                <span class="stg-input-prefix">₺</span>
                                <input type="text" class="stg-input" name="settings[shipping_free_limit]"
                                       value="{{ $s('shipping_free_limit', '500') }}" placeholder="500">
                            </div>
                            <small class="stg-hint">Bu tutar ve üzeri siparişlerde kargo ücretsiz</small>
                        </div>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Minimum Sipariş Tutarı (₺)</label>
                        <div class="stg-input-group">
                            <span class="stg-input-prefix">₺</span>
                            <input type="text" class="stg-input" name="settings[min_order_amount]"
                                   value="{{ $s('min_order_amount', '100') }}" placeholder="100">
                        </div>
                        <small class="stg-hint">Bu tutarın altındaki siparişler kabul edilmez (0 = sınırsız)</small>
                    </div>
                </div>

                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>Ödeme Ayarları</h6>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Para Birimi</label>
                        <input type="text" class="stg-input" name="settings[currency]"
                               value="{{ $s('currency', 'TRY') }}" placeholder="TRY" readonly>
                        <small class="stg-hint">Şu an sadece Türk Lirası desteklenmektedir</small>
                    </div>

                    <div class="stg-toggle-item">
                        <div class="stg-toggle-info">
                            <span>Kapıda Ödeme</span>
                            <small>Müşteriler kapıda nakit/POS ile ödeme yapabilir</small>
                        </div>
                        <label class="stg-switch">
                            <input type="hidden" name="settings[cod_enabled]" value="0">
                            <input type="checkbox" name="settings[cod_enabled]" value="1"
                                   {{ $s('cod_enabled', '1') === '1' ? 'checked' : '' }}>
                            <span class="stg-switch-slider"></span>
                        </label>
                    </div>

                    <div class="stg-toggle-item">
                        <div class="stg-toggle-info">
                            <span>Misafir Sipariş</span>
                            <small>Üye olmadan sipariş verilmesine izin ver</small>
                        </div>
                        <label class="stg-switch">
                            <input type="hidden" name="settings[guest_checkout_enabled]" value="0">
                            <input type="checkbox" name="settings[guest_checkout_enabled]" value="1"
                                   {{ $s('guest_checkout_enabled', '1') === '1' ? 'checked' : '' }}>
                            <span class="stg-switch-slider"></span>
                        </label>
                    </div>

                    <div class="stg-toggle-item">
                        <div class="stg-toggle-info">
                            <span>WhatsApp Sipariş Bildirimi</span>
                            <small>Yeni sipariş geldiğinde admin e-postasına WhatsApp iletişim linki gönder</small>
                        </div>
                        <label class="stg-switch">
                            <input type="hidden" name="settings[order_whatsapp_notification]" value="0">
                            <input type="checkbox" name="settings[order_whatsapp_notification]" value="1"
                                   {{ $s('order_whatsapp_notification', '1') === '1' ? 'checked' : '' }}>
                            <span class="stg-switch-slider"></span>
                        </label>
                    </div>
                </div>
            </form>
        </div>

        {{-- ══════════════ 6. GÖRÜNÜM ══════════════ --}}
        <div class="stg-panel" id="stg-appearance">
            <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="stg-panel-header">
                    <div>
                        <h5><i class="bi bi-palette"></i> Görünüm Ayarları</h5>
                        <p>Tema ve düzen tercihleri</p>
                    </div>
                    <button type="submit" class="stg-save-btn"><i class="bi bi-check-lg"></i> Kaydet</button>
                </div>

                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>OG Görseli</h6>
                        <p>Sosyal medyada paylaşıldığında görünecek varsayılan görsel</p>
                    </div>

                    <div class="stg-field">
                        <div class="stg-logo-upload">
                            <div class="stg-logo-preview">
                                @if($s('og_image'))
                                <a href="{{ upload_url($s('og_image')) }}" class="glightbox" data-gallery="settings" data-title="OG Görseli">
                                    <img class="stg-logo-img" id="ogImagePreview" src="{{ upload_url($s('og_image')) }}" alt="OG Image">
                                </a>
                                @else
                                <div class="stg-logo-current"><i class="bi bi-image"></i></div>
                                <img class="stg-logo-img d-none" id="ogImagePreview" src="" alt="OG Image">
                                @endif
                            </div>
                            <div class="stg-logo-actions">
                                <input type="file" id="ogImageInput" name="files[og_image]" accept="image/*" hidden>
                                <button type="button" class="stg-btn stg-btn-sm" onclick="document.getElementById('ogImageInput').click()">
                                    <i class="bi bi-upload"></i> Görsel Yükle
                                </button>
                                <small class="text-muted">1200x630px boyutunda PNG veya JPG önerilir</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>Genel Tercihler</h6>
                    </div>

                    <div class="stg-toggle-list">
                        <div class="stg-toggle-item">
                            <div class="stg-toggle-info">
                                <span>Kullanıcı Kaydı</span>
                                <small>Yeni kullanıcıların kayıt olmasına izin ver</small>
                            </div>
                            <label class="stg-switch">
                                <input type="hidden" name="settings[registration_enabled]" value="0">
                                <input type="checkbox" name="settings[registration_enabled]" value="1"
                                       {{ $s('registration_enabled', '1') === '1' ? 'checked' : '' }}>
                                <span class="stg-switch-slider"></span>
                            </label>
                        </div>
                        <div class="stg-toggle-item">
                            <div class="stg-toggle-info">
                                <span>Bakım Modu</span>
                                <small>Siteyi bakım moduna al (admin paneli ve giriş sayfası her zaman erişilebilir)</small>
                            </div>
                            <label class="stg-switch">
                                <input type="hidden" name="settings[maintenance_mode]" value="0">
                                <input type="checkbox" name="settings[maintenance_mode]" value="1"
                                       {{ $s('maintenance_mode') === '1' ? 'checked' : '' }}
                                       onchange="document.getElementById('maintenanceDetails').classList.toggle('d-none', !this.checked)">
                                <span class="stg-switch-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>

                <div id="maintenanceDetails" class="{{ $s('maintenance_mode') === '1' ? '' : 'd-none' }}">
                    <div class="stg-section">
                        <div class="stg-section-title">
                            <h6>Bakım Modu Ayarları</h6>
                            <p>Bakım modu aktifken ziyaretçilere gösterilecek mesaj ve erişim izinleri</p>
                        </div>

                        <div class="stg-field">
                            <label class="stg-label">Bakım Modu Mesajı</label>
                            <textarea name="settings[maintenance_message]" class="stg-input" rows="3"
                                      placeholder="Sitemiz şu anda planlı bakım çalışması nedeniyle geçici olarak kullanım dışıdır.">{{ $s('maintenance_message') }}</textarea>
                            <small class="stg-hint">Boş bırakılırsa varsayılan mesaj gösterilir</small>
                        </div>

                        <div class="stg-field">
                            <label class="stg-label">İzin Verilen IP Adresleri</label>
                            <textarea name="settings[maintenance_allowed_ips]" class="stg-input" rows="4"
                                      placeholder="192.168.1.1&#10;10.0.0.1">{{ $s('maintenance_allowed_ips') }}</textarea>
                            <small class="stg-hint">Her satıra bir IP adresi yazın. Bu IP'lerden gelen ziyaretçiler bakım modunda da siteyi görebilir.</small>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        {{-- ══════════════ 7. E-POSTA (SMTP) ══════════════ --}}
        <div class="stg-panel" id="stg-email">
            <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="stg-panel-header">
                    <div>
                        <h5><i class="bi bi-envelope-at"></i> E-posta (SMTP) Ayarları</h5>
                        <p>Giden e-posta sunucusu yapılandırması</p>
                    </div>
                    <button type="submit" class="stg-save-btn"><i class="bi bi-check-lg"></i> Kaydet</button>
                </div>

                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>SMTP Sunucusu</h6>
                    </div>

                    <div class="stg-row">
                        <div class="stg-field stg-field-wide">
                            <label class="stg-label">SMTP Sunucu Adresi</label>
                            <input type="text" class="stg-input" name="settings[mail_host]"
                                   value="{{ $s('mail_host') }}" placeholder="smtp.example.com">
                        </div>
                        <div class="stg-field flex-1">
                            <label class="stg-label">Port</label>
                            <input type="number" class="stg-input" name="settings[mail_port]"
                                   value="{{ $s('mail_port', '587') }}" placeholder="587">
                        </div>
                    </div>

                    <div class="stg-row">
                        <div class="stg-field stg-half">
                            <label class="stg-label">Kullanıcı Adı</label>
                            <input type="text" class="stg-input" name="settings[mail_username]"
                                   value="{{ $s('mail_username') }}" placeholder="user@domain.com">
                        </div>
                        <div class="stg-field stg-half">
                            <label class="stg-label">Şifre</label>
                            <input type="password" class="stg-input" name="settings[mail_password]"
                                   value="{{ $s('mail_password') }}" placeholder="SMTP şifresi">
                        </div>
                    </div>

                    <div class="stg-row">
                        <div class="stg-field stg-half">
                            <label class="stg-label">Şifreleme</label>
                            <select class="stg-select" name="settings[mail_encryption]">
                                <option value="tls" {{ $s('mail_encryption', 'tls') === 'tls' ? 'selected' : '' }}>TLS</option>
                                <option value="ssl" {{ $s('mail_encryption') === 'ssl' ? 'selected' : '' }}>SSL</option>
                                <option value="none" {{ $s('mail_encryption') === 'none' ? 'selected' : '' }}>Yok</option>
                            </select>
                        </div>
                        <div class="stg-field stg-half">
                            <label class="stg-label">Gönderen Adı</label>
                            <input type="text" class="stg-input" name="settings[mail_from_name]"
                                   value="{{ $s('mail_from_name') }}" placeholder="Gönderen adı">
                        </div>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Gönderen E-posta</label>
                        <input type="email" class="stg-input" name="settings[mail_from_address]"
                               value="{{ $s('mail_from_address') }}" placeholder="noreply@domain.com">
                    </div>

                </div>

                {{-- Test E-postası Gönder --}}
                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>Test E-postası Gönder</h6>
                        <p>SMTP ayarlarının doğru çalışıp çalışmadığını test edin</p>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Alıcı E-posta</label>
                        <input type="email" class="stg-input" placeholder="test@example.com" id="testEmailInput">
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Konu</label>
                        <input type="text" class="stg-input" id="testEmailSubject"
                               value="{{ $s('site_name', config('app.name')) }} — E-posta Bilgilendirmesi">
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Mesaj</label>
                        <textarea class="stg-textarea" id="testEmailMessage" rows="5">Merhaba, bu e-posta {{ $s('site_name', config('app.name')) }} platformu üzerinden gönderilmiştir. E-posta yapılandırmanız başarıyla tamamlanmıştır. Herhangi bir sorunuz olursa bizimle iletişime geçebilirsiniz.</textarea>
                    </div>

                    <button type="button" class="stg-btn stg-btn-sm" id="sendTestEmailBtn"><i class="bi bi-send"></i> Test Maili Gönder</button>
                </div>

                {{-- Mail Logosu --}}
                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>Mail Logosu</h6>
                        <p>Giden e-postalara gömülecek logo (CID olarak eklenir, her mail istemcisinde görünür)</p>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Logo Görseli</label>
                        <div class="stg-logo-upload">
                            <div class="stg-logo-preview">
                                @if($s('mail_logo'))
                                <a href="{{ upload_url($s('mail_logo')) }}" class="glightbox" data-gallery="settings" data-title="Mail Logosu">
                                    <img class="stg-logo-img" id="mailLogoPreviewImg" src="{{ upload_url($s('mail_logo')) }}" alt="Mail Logo">
                                </a>
                                @else
                                <div class="stg-logo-current" id="mailLogoDefault"><i class="bi bi-envelope-paper"></i></div>
                                <img class="stg-logo-img d-none" id="mailLogoPreviewImg" src="" alt="Mail Logo">
                                @endif
                            </div>
                            <div class="stg-logo-actions">
                                <input type="file" id="mailLogoFileInput" name="files[mail_logo]" accept="image/png,image/jpeg,image/webp" hidden>
                                <button type="button" class="stg-btn stg-btn-sm" onclick="document.getElementById('mailLogoFileInput').click()">
                                    <i class="bi bi-upload"></i> Logo Yükle
                                </button>
                                @if($s('mail_logo'))
                                <button type="button" class="stg-btn stg-btn-sm stg-btn-danger" id="mailLogoRemoveBtn" title="Kaldır">
                                    <i class="bi bi-trash3"></i> Kaldır
                                </button>
                                @endif
                                <small class="text-muted">PNG veya JPG. Maks. 1 MB. Önerilen boyut: 400×400px</small>
                            </div>
                        </div>
                        <input type="hidden" name="settings[mail_logo_remove]" id="mailLogoRemoveInput" value="0">
                    </div>
                </div>

                {{-- Gönderim Modu --}}
                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>Gönderim Modu</h6>
                        <p>Maillerin gerçek alıcılara mı yoksa test adreslerine mi gideceğini belirleyin</p>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Mod Seçimi</label>
                        <div class="stg-mode-cards">
                            <label class="stg-mode-card {{ $s('mail_mode', 'normal') === 'normal' ? 'active' : '' }}">
                                <input type="radio" name="settings[mail_mode]" value="normal"
                                       {{ $s('mail_mode', 'normal') === 'normal' ? 'checked' : '' }}>
                                <i class="bi bi-send-check"></i>
                                <strong>Normal Mod</strong>
                                <small>Mailler asıl alıcıya gider</small>
                            </label>
                            <label class="stg-mode-card {{ $s('mail_mode') === 'developer' ? 'active' : '' }}">
                                <input type="radio" name="settings[mail_mode]" value="developer"
                                       {{ $s('mail_mode') === 'developer' ? 'checked' : '' }}>
                                <i class="bi bi-bug"></i>
                                <strong>Developer / Test Mod</strong>
                                <small>Tüm mailler test adreslerine yönlendirilir</small>
                            </label>
                        </div>
                    </div>

                    <div class="stg-field stg-mail-test-addresses {{ $s('mail_mode') === 'developer' ? '' : 'd-none' }}" id="mailTestAddressesField">
                        <label class="stg-label">Test E-posta Adresleri</label>
                        <input type="text" class="stg-input" name="settings[mail_test_addresses]"
                               value="{{ $s('mail_test_addresses') }}" placeholder="ornek@gmail.com,diger@gmail.com">
                        <small class="stg-hint">Virgülle ayırarak birden fazla adres yazabilirsiniz. Tüm giden mailler bu adreslere yönlendirilecektir.</small>
                    </div>
                </div>
            </form>
        </div>

        {{-- ══════════════ 8. MAİL TEMASI ══════════════ --}}
        <div class="stg-panel" id="stg-mail-theme">
            <form method="POST" action="{{ route('admin.settings.update') }}">
                @csrf
                @method('PUT')

                <div class="stg-panel-header">
                    <div>
                        <h5>Mail Teması</h5>
                        <p>E-posta şablonlarının renk paleti, footer yazısı ve sosyal medya ayarları</p>
                    </div>
                    <button type="submit" class="stg-save-btn"><i class="bi bi-check-lg"></i> Kaydet</button>
                </div>

                {{-- Renk Paleti --}}
                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>Renk Paleti</h6>
                        <p>E-posta şablonlarında kullanılan ana renkleri özelleştirin</p>
                    </div>

                    <div class="stg-row">
                        <div class="stg-field stg-half">
                            <label class="stg-label">Ana Renk (Primary)</label>
                            <div class="stg-color-field">
                                <input type="color" class="stg-color-picker" id="mtPrimaryColor"
                                       name="settings[mail_theme_primary_color]"
                                       value="{{ $s('mail_theme_primary_color', '#4a7c43') }}">
                                <input type="text" class="stg-input stg-color-hex" id="mtPrimaryColorHex"
                                       value="{{ $s('mail_theme_primary_color', '#4a7c43') }}" maxlength="7" readonly>
                            </div>
                        </div>
                        <div class="stg-field stg-half">
                            <label class="stg-label">Koyu Ana Renk (Primary Dark)</label>
                            <div class="stg-color-field">
                                <input type="color" class="stg-color-picker" id="mtPrimaryDarkColor"
                                       name="settings[mail_theme_primary_dark_color]"
                                       value="{{ $s('mail_theme_primary_dark_color', '#2d5a27') }}">
                                <input type="text" class="stg-input stg-color-hex" id="mtPrimaryDarkColorHex"
                                       value="{{ $s('mail_theme_primary_dark_color', '#2d5a27') }}" maxlength="7" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="stg-row">
                        <div class="stg-field stg-half">
                            <label class="stg-label">Arka Plan Rengi</label>
                            <div class="stg-color-field">
                                <input type="color" class="stg-color-picker" id="mtBgColor"
                                       name="settings[mail_theme_bg_color]"
                                       value="{{ $s('mail_theme_bg_color', '#f0f4e8') }}">
                                <input type="text" class="stg-input stg-color-hex" id="mtBgColorHex"
                                       value="{{ $s('mail_theme_bg_color', '#f0f4e8') }}" maxlength="7" readonly>
                            </div>
                        </div>
                        <div class="stg-field stg-half">
                            <label class="stg-label">Kart Arka Planı</label>
                            <div class="stg-color-field">
                                <input type="color" class="stg-color-picker" id="mtCardBgColor"
                                       name="settings[mail_theme_card_bg_color]"
                                       value="{{ $s('mail_theme_card_bg_color', '#ffffff') }}">
                                <input type="text" class="stg-input stg-color-hex" id="mtCardBgColorHex"
                                       value="{{ $s('mail_theme_card_bg_color', '#ffffff') }}" maxlength="7" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="stg-row">
                        <div class="stg-field stg-half">
                            <label class="stg-label">Metin Rengi</label>
                            <div class="stg-color-field">
                                <input type="color" class="stg-color-picker" id="mtTextColor"
                                       name="settings[mail_theme_text_color]"
                                       value="{{ $s('mail_theme_text_color', '#4a4a4a') }}">
                                <input type="text" class="stg-input stg-color-hex" id="mtTextColorHex"
                                       value="{{ $s('mail_theme_text_color', '#4a4a4a') }}" maxlength="7" readonly>
                            </div>
                        </div>
                        <div class="stg-field stg-half">
                            <label class="stg-label">Soluk Metin Rengi</label>
                            <div class="stg-color-field">
                                <input type="color" class="stg-color-picker" id="mtMutedColor"
                                       name="settings[mail_theme_muted_color]"
                                       value="{{ $s('mail_theme_muted_color', '#888888') }}">
                                <input type="text" class="stg-input stg-color-hex" id="mtMutedColorHex"
                                       value="{{ $s('mail_theme_muted_color', '#888888') }}" maxlength="7" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Footer Ayarları --}}
                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>Footer Ayarları</h6>
                        <p>E-posta alt bilgi yazısı ve sosyal medya bağlantıları</p>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Footer Yazısı</label>
                        <textarea class="stg-textarea" name="settings[mail_theme_footer_text]" rows="3"
                                  id="mtFooterText"
                                  placeholder="Çiftliğimizden sofranıza, doğallığın en taze hali.">{{ $s('mail_theme_footer_text', 'Çiftliğimizden sofranıza, doğallığın en taze hali.') }}</textarea>
                        <small class="stg-hint">E-posta footer bölümünde görünecek açıklama metni</small>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Sosyal Medya Linkleri</label>
                        <div class="stg-switch-row">
                            <label class="stg-switch">
                                <input type="hidden" name="settings[mail_theme_social_links]" value="0">
                                <input type="checkbox" name="settings[mail_theme_social_links]" value="1"
                                       id="mtSocialLinks"
                                       {{ $s('mail_theme_social_links', '1') === '1' ? 'checked' : '' }}>
                                <span class="stg-switch-slider"></span>
                            </label>
                            <span class="stg-switch-text">Footer'da sosyal medya ikonlarını göster</span>
                        </div>
                        <small class="stg-hint">Sosyal Medya sekmesinde tanımlanan hesaplar kullanılır</small>
                    </div>
                </div>

                {{-- Canlı Önizleme --}}
                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>Canlı Önizleme</h6>
                        <p>Yukarıdaki renk ayarlarını değiştirdikçe önizleme otomatik güncellenir</p>
                    </div>

                    <div class="stg-field">
                        <div class="stg-mail-preview-wrap" id="mailThemePreviewWrap">
                            <div class="stg-mail-preview" id="mailThemePreview">
                                {{-- Header --}}
                                <div class="stg-mp-header" id="mpHeader">
                                    @if($s('mail_logo'))
                                    <img src="{{ upload_url($s('mail_logo')) }}" alt="Logo" class="stg-mp-logo-img" id="mpLogoImg">
                                    @else
                                    <img src="" alt="Logo" class="stg-mp-logo-img d-none" id="mpLogoImg">
                                    @endif
                                    <span class="stg-mp-logo {{ $s('mail_logo') ? 'd-none' : '' }}" id="mpLogoText">&#127807; {{ $s('site_name', config('app.name')) }}</span>
                                </div>
                                {{-- Accent --}}
                                <div class="stg-mp-accent" id="mpAccent"></div>
                                {{-- Content --}}
                                <div class="stg-mp-content" id="mpContent">
                                    <p class="stg-mp-greeting" id="mpGreeting">MERHABA</p>
                                    <p class="stg-mp-heading" id="mpHeading">Örnek E-posta Başlığı</p>
                                    <p class="stg-mp-text" id="mpText">Bu bir önizleme metnidir. E-postalarınız bu şekilde görünecektir. Renkleri yukarıdan değiştirerek sonucu anında görebilirsiniz.</p>
                                    <div class="stg-mp-btn-wrap">
                                        <span class="stg-mp-btn" id="mpBtn">Detayları Gör</span>
                                    </div>
                                    <p class="stg-mp-muted" id="mpMuted">Bu e-posta {{ $s('site_name', config('app.name')) }} tarafından gönderilmiştir.</p>
                                </div>
                                {{-- Footer --}}
                                <div class="stg-mp-footer" id="mpFooter">
                                    <p class="stg-mp-footer-link" id="mpFooterLink">{{ $s('site_name', config('app.name')) }}</p>
                                    <p class="stg-mp-footer-text" id="mpFooterText">{{ $s('mail_theme_footer_text', 'Çiftliğimizden sofranıza, doğallığın en taze hali.') }}</p>
                                    <div class="stg-mp-social" id="mpSocial">
                                        <span class="stg-mp-social-icon"><i class="bi bi-facebook"></i></span>
                                        <span class="stg-mp-social-icon"><i class="bi bi-instagram"></i></span>
                                        <span class="stg-mp-social-icon"><i class="bi bi-youtube"></i></span>
                                    </div>
                                    <p class="stg-mp-footer-copy" id="mpFooterCopy">&copy; {{ date('Y') }} {{ $s('site_name', config('app.name')) }}. Tüm hakları saklıdır.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </form>
        </div>

        {{-- ══════════════ 9. reCAPTCHA ══════════════ --}}
        <div class="stg-panel" id="stg-recaptcha">
            <form method="POST" action="{{ route('admin.settings.update') }}">
                @csrf
                @method('PUT')

                <div class="stg-panel-header">
                    <div>
                        <h5><i class="bi bi-shield-check"></i> Google reCAPTCHA v2</h5>
                        <p>Form spam koruması için Google reCAPTCHA onay kutusu ayarları</p>
                    </div>
                    <button type="submit" class="stg-save-btn"><i class="bi bi-check-lg"></i> Kaydet</button>
                </div>

                {{-- reCAPTCHA Durumu --}}
                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>reCAPTCHA Durumu</h6>
                        <p>Form doğrulamasını açıp kapatabilirsiniz</p>
                    </div>

                    <div class="stg-toggle-list">
                        <div class="stg-toggle-item">
                            <div class="stg-toggle-info">
                                <span>reCAPTCHA Doğrulama</span>
                                <small>Açık olduğunda formlarda "Ben robot değilim" onay kutusu gösterilir</small>
                            </div>
                            <label class="stg-switch">
                                <input type="hidden" name="settings[recaptcha_enabled]" value="0">
                                <input type="checkbox" name="settings[recaptcha_enabled]" value="1"
                                       {{ $s('recaptcha_enabled', '0') === '1' ? 'checked' : '' }}>
                                <span class="stg-switch-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>

                {{-- API Anahtarları --}}
                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>API Anahtarları</h6>
                        <p>Google reCAPTCHA v2 "I'm not a robot" Checkbox anahtarları</p>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Site Key (Public Key)</label>
                        <input type="text" class="stg-input" name="settings[recaptcha_site_key]"
                               value="{{ $s('recaptcha_site_key') }}" placeholder="6Lc...">
                        <small class="stg-hint">Google reCAPTCHA admin panelinden aldığınız site anahtarı</small>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Secret Key (Private Key)</label>
                        <input type="password" class="stg-input" name="settings[recaptcha_secret_key]"
                               value="" placeholder="{{ $s('recaptcha_secret_key') ? '●●●●●●●● (değiştirmek için yeni key girin)' : '' }}">
                        <small class="stg-hint">Google reCAPTCHA admin panelinden aldığınız gizli anahtar</small>
                    </div>
                </div>

            </form>
        </div>

        {{-- ══════════════ 10. GOOGLE HARİTALAR ══════════════ --}}
        <div class="stg-panel" id="stg-google-maps">
            <form method="POST" action="{{ route('admin.settings.update') }}">
                @csrf
                @method('PUT')

                <div class="stg-panel-header">
                    <div>
                        <h5><i class="bi bi-geo-alt"></i> Google Haritalar & Yorumlar</h5>
                        <p>Google Places API yapılandırması ve Google yorumlarını çekme ayarları</p>
                    </div>
                    <button type="submit" class="stg-save-btn"><i class="bi bi-check-lg"></i> Kaydet</button>
                </div>

                {{-- API Yapılandırması --}}
                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>API Yapılandırması</h6>
                        <p>Google Cloud Console üzerinden aldığınız Places API (New) anahtarları</p>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">API Key</label>
                        <input type="password" class="stg-input" name="settings[google_places_api_key]"
                               value="" placeholder="{{ $s('google_places_api_key') ? '●●●●●●●● (değiştirmek için yeni key girin)' : 'AIza...' }}">
                        <small class="stg-hint">Google Cloud Console → Credentials bölümünden oluşturulan API anahtarı</small>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Place ID</label>
                        <input type="text" class="stg-input" name="settings[google_places_place_id]"
                               value="{{ $s('google_places_place_id') }}" placeholder="ChIJ...">
                        <small class="stg-hint">İşletmenizin Google Maps Place ID değeri. <a href="https://developers.google.com/maps/documentation/places/web-service/place-id" target="_blank" rel="noopener">Place ID Finder</a> ile bulabilirsiniz.</small>
                    </div>
                </div>

                {{-- Bağlantı Durumu --}}
                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>Bağlantı Testi</h6>
                        <p>API yapılandırmasını doğrulamak için bağlantıyı test edin</p>
                    </div>

                    <button type="button" class="stg-btn" id="testGoogleApiBtn">
                        <i class="bi bi-arrow-repeat"></i> Bağlantıyı Test Et
                    </button>
                    <div id="googleApiTestResult" class="mt-2"></div>
                </div>

            </form>
        </div>

        {{-- ══════════════ 11. YOUTUBE ══════════════ --}}
        <div class="stg-panel" id="stg-youtube">
            <form method="POST" action="{{ route('admin.settings.update') }}">
                @csrf
                @method('PUT')

                <div class="stg-panel-header">
                    <div>
                        <h5><i class="bi bi-youtube"></i> YouTube Entegrasyonu</h5>
                        <p>YouTube Data API v3 yapılandırması ve kanal videoları ayarları</p>
                    </div>
                    <button type="submit" class="stg-save-btn"><i class="bi bi-check-lg"></i> Kaydet</button>
                </div>

                {{-- API Yapılandırması --}}
                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>API Yapılandırması</h6>
                        <p>Google Cloud Console üzerinden YouTube Data API v3 anahtarınızı girin</p>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">API Key</label>
                        <input type="password" class="stg-input" name="settings[youtube_api_key]"
                               value="" placeholder="{{ $s('youtube_api_key') ? '●●●●●●●● (değiştirmek için yeni key girin)' : 'AIza...' }}">
                        <small class="stg-hint">Google Cloud Console → Credentials → YouTube Data API v3 için oluşturulan API anahtarı</small>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Channel ID</label>
                        <input type="text" class="stg-input" name="settings[youtube_channel_id]"
                               value="{{ $s('youtube_channel_id') }}" placeholder="UCxxxxxxxxxxxxxxxxxxxxxxxx">
                        <small class="stg-hint">YouTube kanalınızın ID değeri. Kanal URL'sinden veya YouTube Studio'dan bulabilirsiniz.</small>
                    </div>
                </div>

                {{-- Bağlantı Testi --}}
                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>Bağlantı Testi</h6>
                        <p>API yapılandırmasını doğrulamak için bağlantıyı test edin</p>
                    </div>

                    <button type="button" class="stg-btn" id="testYoutubeApiBtn">
                        <i class="bi bi-arrow-repeat"></i> Bağlantıyı Test Et
                    </button>
                </div>

            </form>
        </div>

        {{-- ══════════════ INSTAGRAM ENTEGRASYONU ══════════════ --}}
        <div class="stg-panel" id="stg-instagram">
            <form method="POST" action="{{ route('admin.settings.update') }}">
                @csrf
                @method('PUT')

                <div class="stg-panel-header">
                    <div>
                        <h5><i class="bi bi-instagram"></i> Instagram Entegrasyonu</h5>
                        <p>Meta Graph API v21 ile zamanlanmış Instagram gönderilerini otomatik yayınla</p>
                    </div>
                    <button type="submit" class="stg-save-btn"><i class="bi bi-check-lg"></i> Kaydet</button>
                </div>

                {{-- Token Durumu --}}
                @if(isset($instagramTokenStatus) && $instagramTokenStatus['level'] !== 'missing')
                @php
                    $tokenLevel = $instagramTokenStatus['level'];
                    $tokenBoxClass = match($tokenLevel) {
                        'expired'  => 'stg-token-status stg-token-status--expired',
                        'critical' => 'stg-token-status stg-token-status--critical',
                        'warning'  => 'stg-token-status stg-token-status--warning',
                        'ok'       => 'stg-token-status stg-token-status--ok',
                        default    => 'stg-token-status stg-token-status--unknown',
                    };
                    $tokenIcon = match($tokenLevel) {
                        'expired'  => 'bi-x-octagon-fill',
                        'critical' => 'bi-exclamation-triangle-fill',
                        'warning'  => 'bi-clock-history',
                        'ok'       => 'bi-shield-check',
                        default    => 'bi-question-circle',
                    };
                @endphp
                <div class="stg-section">
                    <div class="{{ $tokenBoxClass }}">
                        <div class="stg-token-status__icon">
                            <i class="bi {{ $tokenIcon }}"></i>
                        </div>
                        <div class="stg-token-status__body">
                            <div class="stg-token-status__title">{{ $instagramTokenStatus['message'] }}</div>
                            @if(! empty($instagramTokenStatus['expires_at']))
                                <div class="stg-token-status__detail">
                                    Bitiş: <strong>{{ $instagramTokenStatus['expires_at']->translatedFormat('d M Y H:i') }}</strong>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                @endif

                {{-- Durum --}}
                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>Otomatik Paylaşım Durumu</h6>
                        <p>Cron tarafından planlanmış gönderilerin yayınlanmasını etkinleştir veya duraklat</p>
                    </div>

                    <div class="stg-field">
                        <div class="stg-switch-row">
                            @php $instaEnabled = $s('instagram_enabled', '0') === '1'; @endphp
                            <div class="form-check form-switch">
                                <input type="hidden" name="settings[instagram_enabled]" value="0">
                                <input type="checkbox" class="form-check-input" role="switch"
                                       id="instagramEnabledSwitch" name="settings[instagram_enabled]"
                                       value="1" {{ $instaEnabled ? 'checked' : '' }}>
                            </div>
                            <span class="stg-switch-text">Otomatik paylaşımı etkinleştir (scheduler)</span>
                        </div>
                        <small class="stg-hint">Kapalıyken planlanan gönderiler cron tarafından yayınlanmaz. Manuel "Şimdi Paylaş" butonu bu ayardan bağımsız çalışır.</small>
                    </div>
                </div>

                {{-- API Yapılandırması --}}
                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>Meta Graph API Anahtarları</h6>
                        <p>Facebook for Developers → My Apps → Instagram Graph API üzerinden alınır</p>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Instagram Business/Creator Account ID</label>
                        <input type="text" class="stg-input" name="settings[instagram_user_id]"
                               value="{{ $s('instagram_user_id') }}" placeholder="17841XXXXXXXXXXXX">
                        <small class="stg-hint">Instagram Business veya Creator hesabınızın sayısal ID'si (Graph API Explorer üzerinden alınır)</small>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Bağlı Facebook Sayfa ID'si</label>
                        <input type="text" class="stg-input" name="settings[instagram_facebook_page_id]"
                               value="{{ $s('instagram_facebook_page_id') }}" placeholder="1234567890">
                        <small class="stg-hint">Instagram hesabınıza bağlı Facebook Sayfası ID'si</small>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Long-Lived Access Token</label>
                        <input type="password" class="stg-input" name="settings[instagram_access_token]"
                               value="" placeholder="{{ $s('instagram_access_token') ? '●●●●●●●● (değiştirmek için yeni token girin)' : 'EAAxxxxxxxxxxxxxxxx...' }}">
                        <small class="stg-hint">Uzun ömürlü access token (60 gün geçerli). Süresi dolmadan yenilenmelidir. Boş bırakırsanız mevcut token korunur.</small>
                    </div>

                    <div class="stg-row">
                        <div class="stg-field stg-half">
                            <label class="stg-label">App ID</label>
                            <input type="text" class="stg-input" name="settings[instagram_app_id]"
                                   value="{{ $s('instagram_app_id') }}" placeholder="1234567890123456">
                            <small class="stg-hint">Meta for Developers uygulama ID'si</small>
                        </div>
                        <div class="stg-field stg-half">
                            <label class="stg-label">App Secret</label>
                            <input type="password" class="stg-input" name="settings[instagram_app_secret]"
                                   value="" placeholder="{{ $s('instagram_app_secret') ? '●●●●●●●● (değiştirmek için yeni secret girin)' : '32 karakter hex' }}">
                            <small class="stg-hint">Uygulamanın secret değeri (token yenileme için gerekli)</small>
                        </div>
                    </div>
                </div>

                {{-- Username (preview için) --}}
                <div class="stg-field">
                    <label class="stg-label">Instagram Kullanıcı Adı (önizleme için)</label>
                    <input type="text" class="stg-input" name="settings[instagram_username]"
                           value="{{ $s('instagram_username', 'orhanbabaninciftligi') }}"
                           placeholder="orhanbabaninciftligi">
                    <small class="stg-hint">Form sayfasındaki Instagram önizleme kartında gösterilir</small>
                </div>

                {{-- Bağlantı Testi --}}
                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>Bağlantı Testi</h6>
                        <p>Token ve hesap bilgisini Meta Graph API üzerinden doğrula</p>
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                        <button type="button" class="stg-btn" id="testInstagramApiBtn">
                            <i class="bi bi-arrow-repeat"></i> Bağlantıyı Test Et
                        </button>
                        <button type="button" class="stg-btn" id="refreshInstagramTokenBtn">
                            <i class="bi bi-shield-lock"></i> Token'ı Yenile (60 gün uzat)
                        </button>
                    </div>
                    <small class="stg-hint mt-2 d-block">"Token'ı Yenile" butonu App ID + App Secret + mevcut token kullanarak yeni uzun ömürlü token üretir ve otomatik olarak kaydeder.</small>
                </div>

                {{-- Facebook Page Cross-Post --}}
                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6><i class="bi bi-facebook me-1 text-info"></i> Facebook Page Cross-Post</h6>
                        <p>Instagram post'unu aynı anda Facebook sayfasında da yayınla</p>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Facebook Page Token <small class="text-muted">(otomatik alınır)</small></label>
                        <input type="password" class="stg-input" name="settings[instagram_facebook_page_token]"
                               value="" placeholder="{{ $s('instagram_facebook_page_token') ? '●●●●●●●● (alındı, değiştirmek için yeni gir)' : 'Aşağıdaki butonla otomatik al' }}">
                        <small class="stg-hint">User token'dan farklı, kalıcı (süresiz) page token. <strong>Otomatik almak için aşağıdaki butona bas.</strong></small>
                    </div>

                    <div class="d-flex gap-2 flex-wrap mt-3">
                        <button type="button" class="stg-btn" id="fetchFacebookPageTokenBtn">
                            <i class="bi bi-key-fill"></i> Page Token'ı Otomatik Al
                        </button>
                        <button type="button" class="stg-btn" id="testFacebookApiBtn">
                            <i class="bi bi-facebook"></i> Facebook Bağlantısını Test Et
                        </button>
                    </div>
                    <small class="stg-hint mt-2 d-block">
                        "Otomatik Al" butonu User Access Token + Page ID kullanarak Page Token'ı çeker ve kaydeder.
                        Önce User Token + Page ID dolu olmalı. Page Token kalıcıdır, yenilemeye gerek yok.
                    </small>
                </div>

                {{-- Rehber --}}
                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>Nasıl Anahtar Alırım?</h6>
                        <p>Adım adım Meta Graph API kurulumu</p>
                    </div>
                    <div class="stg-hint" style="line-height:1.9">
                        <strong>1.</strong> Instagram hesabını <em>Business</em> veya <em>Creator</em> hesabına çevir (mobil uygulama → Ayarlar → Hesap türüne geç).<br>
                        <strong>2.</strong> <a href="https://www.facebook.com/pages/create/" target="_blank" rel="noopener" class="text-teal">Facebook Sayfası</a> oluştur ve Instagram hesabını bu sayfaya bağla (Meta Business Suite → Ayarlar → Bağlı hesaplar).<br>
                        <strong>3.</strong> <a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener" class="text-teal">Meta for Developers</a> → <em>Create App</em> → <em>Business</em> tipinde yeni uygulama oluştur.<br>
                        <strong>4.</strong> Uygulamaya <em>Instagram Graph API</em> ve <em>Facebook Login for Business</em> ürünlerini ekle.<br>
                        <strong>5.</strong> <a href="https://developers.facebook.com/tools/explorer/" target="_blank" rel="noopener" class="text-teal">Graph API Explorer</a> ile şu izinleri iste:
                        <code>instagram_basic</code>, <code>instagram_content_publish</code>, <code>pages_show_list</code>, <code>pages_read_engagement</code>, <code>business_management</code>.<br>
                        <strong>6.</strong> Kısa ömürlü token'ı uzun ömürlü'ye çevirmek için: "Token'ı Yenile" butonunu kullan veya Graph API debug aracıyla 60 günlük token üret.<br>
                        <strong>7.</strong> Instagram Business Account ID için Graph API Explorer'da şu isteği çalıştır:
                        <code>GET /{facebook-page-id}?fields=instagram_business_account</code>.<br>
                        <strong>8.</strong> Yukarıdaki alanlara bilgileri yapıştır → <em>Kaydet</em> → <em>Bağlantıyı Test Et</em>.
                    </div>
                </div>

            </form>
        </div>

        {{-- ══════════════ TİKTOK ══════════════ --}}
        <div class="stg-panel" id="stg-tiktok">
            <form method="POST" action="{{ route('admin.settings.update') }}">
                @csrf
                @method('PUT')

                <div class="stg-panel-header">
                    <div class="stg-panel-icon"><i class="bi bi-tiktok"></i></div>
                    <div>
                        <h5><i class="bi bi-tiktok"></i> TikTok Cross-Post</h5>
                        <small class="text-muted">
                            Instagram'a paylaşılan içerikler otomatik olarak TikTok'a da gönderilir
                            (Photo Mode + Video). Detay: <code>docs/tiktok.md</code>
                        </small>
                    </div>
                </div>

                {{-- Audit başvuru rehberi (10 adım + hazır metinler) --}}
                @include('admin.settings._partials.tiktok-audit-guide')

                {{-- Bağlantı durumu --}}
                @php
                    $ttAccessToken = $s('tiktok_access_token', '');
                    $ttExpiresAt   = $s('tiktok_expires_at', '');
                    $ttUsername    = $s('tiktok_username', '');
                    $ttConnected   = trim((string) $ttAccessToken) !== '';

                    $ttExpiresCarbon = null;
                    if ($ttConnected && $ttExpiresAt !== '') {
                        try { $ttExpiresCarbon = \Illuminate\Support\Carbon::parse($ttExpiresAt); }
                        catch (\Throwable) {}
                    }
                @endphp

                @if ($ttConnected)
                    <div class="stg-token-status stg-token-status--ok mb-4">
                        <div class="stg-token-status__icon"><i class="bi bi-check-circle-fill text-success"></i></div>
                        <div class="stg-token-status__body">
                            <div class="stg-token-status__title">
                                @if ($ttUsername)
                                    Bağlı: <strong>&#64;{{ $ttUsername }}</strong>
                                @else
                                    TikTok hesabı bağlı
                                @endif
                            </div>
                            @if ($ttExpiresCarbon)
                                <small class="text-muted">
                                    Token bitiş: <strong>{{ $ttExpiresCarbon->translatedFormat('d M Y H:i') }}</strong>
                                </small>
                            @endif
                        </div>
                        <a href="{{ route('admin.tiktok.oauth.connect') }}" class="btn-glass btn-sm">
                            <i class="bi bi-arrow-repeat"></i> Yeniden Bağla
                        </a>
                    </div>
                @else
                    <div class="alert alert-warning d-flex align-items-center gap-2 mb-4">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <div class="flex-grow-1">
                            <strong>TikTok bağlı değil.</strong>
                            Önce hesabı bağla, sonra ayarları kaydet.
                        </div>
                        <a href="{{ route('admin.tiktok.oauth.connect') }}" class="btn-teal btn-sm">
                            <i class="bi bi-tiktok"></i> TikTok'u Bağla
                        </a>
                    </div>
                @endif

                {{-- Etkinleştirme --}}
                <div class="stg-field-group mb-3">
                    @php $ttEnabled = $s('tiktok_enabled', '0') === '1'; @endphp
                    <label class="stg-toggle">
                        <input type="hidden" name="settings[tiktok_enabled]" value="0">
                        <input type="checkbox" name="settings[tiktok_enabled]" value="1"
                               {{ $ttEnabled ? 'checked' : '' }}>
                        <span class="stg-toggle-slider"></span>
                        <span class="stg-toggle-label">
                            <strong>TikTok cross-post aktif</strong>
                            <small>Kapalıyken hiçbir post TikTok'a gönderilmez</small>
                        </span>
                    </label>
                </div>

                <div class="row g-3">
                    {{-- Mod --}}
                    <div class="col-md-6">
                        <label class="stg-label">API Modu</label>
                        <select class="stg-input" name="settings[tiktok_mode]">
                            <option value="sandbox" {{ $s('tiktok_mode', 'sandbox') === 'sandbox' ? 'selected' : '' }}>
                                Sandbox (test)
                            </option>
                            <option value="production" {{ $s('tiktok_mode') === 'production' ? 'selected' : '' }}>
                                Production
                            </option>
                        </select>
                        <small class="text-muted">Audit onaylanınca Production'a geç.</small>
                    </div>

                    {{-- Yayın modu --}}
                    <div class="col-md-6">
                        <label class="stg-label">Yayın Modu</label>
                        <select class="stg-input" name="settings[tiktok_post_mode]">
                            <option value="inbox" {{ $s('tiktok_post_mode', 'inbox') === 'inbox' ? 'selected' : '' }}>
                                Inbox (mobilde manuel yayın)
                            </option>
                            <option value="direct" {{ $s('tiktok_post_mode') === 'direct' ? 'selected' : '' }}>
                                Direct Post (otomatik yayın — audit gerekli)
                            </option>
                        </select>
                        <small class="text-muted">Audit beklerken Inbox kalmalı.</small>
                    </div>
                </div>

                {{-- Client credentials --}}
                <hr class="my-4">
                <h6 class="mb-3"><i class="bi bi-key me-1"></i> Uygulama Kimlik Bilgileri</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="stg-label">
                            Client Key
                            <i class="bi bi-question-circle ms-1" data-bs-toggle="tooltip"
                               title="Rehberin Adım 3'üne bak. developers.tiktok.com → app detay sayfasında üstte görünür."></i>
                        </label>
                        <input type="text" class="stg-input" name="settings[tiktok_client_key]"
                               value="{{ $s('tiktok_client_key') }}"
                               placeholder="aw1234abcd5678...">
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i>
                            Nereden? <a href="https://developers.tiktok.com" target="_blank" rel="noopener">developers.tiktok.com</a>
                            → Manage apps → app detayı → üst kısımda Client Key. Public bilgi, frontend'de de kullanılabilir.
                        </small>
                    </div>
                    <div class="col-md-6">
                        <label class="stg-label">
                            Client Secret
                            <i class="bi bi-question-circle ms-1" data-bs-toggle="tooltip"
                               title="Tek seferlik gösterilir. Kaybedersen 'Regenerate' tıkla, eski iptal olur."></i>
                        </label>
                        <input type="password" class="stg-input" name="settings[tiktok_client_secret]"
                               value="{{ $s('tiktok_client_secret') }}"
                               placeholder="••••••••" autocomplete="new-password">
                        <small class="text-muted">
                            <i class="bi bi-shield-lock"></i>
                            Hassas bilgi — asla frontend / GitHub'a koyma. Audit log'da otomatik maskelenir.
                            Tek seferlik gösterilir, kaybedersen "Regenerate" gerekir.
                        </small>
                    </div>
                </div>

                {{-- Bilgi: Access Token + Refresh Token otomatik gelir --}}
                <div class="alert alert-info small mt-3 mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    <strong>Access Token + Refresh Token</strong> alanları görünmez —
                    "TikTok'u Bağla" butonuna bastığında OAuth akışıyla otomatik gelir ve
                    güvenli şekilde Setting tablosuna kaydedilir.
                    <br>
                    Refresh token <strong>365 gün</strong> geçerli, access token <strong>24 saat</strong>.
                    Cron her gece 04:15'te otomatik yeniler — sen müdahale etmezsin.
                </div>

                {{-- Otomatik paylaşım --}}
                <hr class="my-4">
                <h6 class="mb-3"><i class="bi bi-magic me-1"></i> Otomatik Paylaşım</h6>
                <div class="stg-field-group mb-3">
                    @php $ttAutoBlog = $s('tiktok_auto_share_blog', '0') === '1'; @endphp
                    <label class="stg-toggle">
                        <input type="hidden" name="settings[tiktok_auto_share_blog]" value="0">
                        <input type="checkbox" name="settings[tiktok_auto_share_blog]" value="1"
                               {{ $ttAutoBlog ? 'checked' : '' }}>
                        <span class="stg-toggle-slider"></span>
                        <span class="stg-toggle-label">
                            <strong>Otomatik blog yazılarını TikTok'a gönder</strong>
                            <small>Cron tarafından üretilen blog post'ları otomatik olarak TT'ye düşer</small>
                        </span>
                    </label>
                </div>

                {{-- Yayın ayarları --}}
                <hr class="my-4">
                <h6 class="mb-3"><i class="bi bi-gear me-1"></i> Varsayılan Yayın Ayarları</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="stg-label">Gizlilik</label>
                        <select class="stg-input" name="settings[tiktok_default_privacy]">
                            <option value="PUBLIC_TO_EVERYONE" {{ $s('tiktok_default_privacy', 'PUBLIC_TO_EVERYONE') === 'PUBLIC_TO_EVERYONE' ? 'selected' : '' }}>Herkese Açık</option>
                            <option value="MUTUAL_FOLLOW_FRIENDS" {{ $s('tiktok_default_privacy') === 'MUTUAL_FOLLOW_FRIENDS' ? 'selected' : '' }}>Karşılıklı Takipçiler</option>
                            <option value="SELF_ONLY" {{ $s('tiktok_default_privacy') === 'SELF_ONLY' ? 'selected' : '' }}>Sadece Ben</option>
                        </select>
                    </div>
                    <div class="col-md-6 d-flex flex-column gap-2 justify-content-center">
                        @php
                            $ttDisableComment = $s('tiktok_disable_comment', '0') === '1';
                            $ttDisableDuet    = $s('tiktok_disable_duet', '0') === '1';
                            $ttDisableStitch  = $s('tiktok_disable_stitch', '0') === '1';
                        @endphp
                        <label class="form-check">
                            <input type="hidden" name="settings[tiktok_disable_comment]" value="0">
                            <input class="form-check-input" type="checkbox" name="settings[tiktok_disable_comment]" value="1" {{ $ttDisableComment ? 'checked' : '' }}>
                            <span class="form-check-label">Yorumları kapat</span>
                        </label>
                        <label class="form-check">
                            <input type="hidden" name="settings[tiktok_disable_duet]" value="0">
                            <input class="form-check-input" type="checkbox" name="settings[tiktok_disable_duet]" value="1" {{ $ttDisableDuet ? 'checked' : '' }}>
                            <span class="form-check-label">Duet'i kapat</span>
                        </label>
                        <label class="form-check">
                            <input type="hidden" name="settings[tiktok_disable_stitch]" value="0">
                            <input class="form-check-input" type="checkbox" name="settings[tiktok_disable_stitch]" value="1" {{ $ttDisableStitch ? 'checked' : '' }}>
                            <span class="form-check-label">Stitch'i kapat</span>
                        </label>
                    </div>
                </div>

                <div class="stg-panel-footer mt-4">
                    <button type="submit" class="btn-teal">
                        <i class="bi bi-check-lg me-1"></i> Ayarları Kaydet
                    </button>
                </div>
            </form>
        </div>

        {{-- ══════════════ TELEGRAM BİLDİRİMLERİ ══════════════ --}}
        <div class="stg-panel" id="stg-telegram">
            <form method="POST" action="{{ route('admin.settings.update') }}">
                @csrf
                @method('PUT')

                <div class="stg-panel-header">
                    <div>
                        <h5><i class="bi bi-telegram"></i> Telegram Bildirimleri</h5>
                        <p>Instagram paylaşımları başarısız olduğunda Telegram'a anında bildirim gönder</p>
                    </div>
                    <button type="submit" class="stg-save-btn"><i class="bi bi-check-lg"></i> Kaydet</button>
                </div>

                {{-- Durum --}}
                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>Bildirim Durumu</h6>
                        <p>Telegram üzerinden hata bildirimlerini etkinleştir veya duraklat</p>
                    </div>

                    <div class="stg-field">
                        <div class="stg-switch-row">
                            @php $tgEnabled = $s('telegram_enabled', '0') === '1'; @endphp
                            <div class="form-check form-switch">
                                <input type="hidden" name="settings[telegram_enabled]" value="0">
                                <input type="checkbox" class="form-check-input" role="switch"
                                       id="telegramEnabledSwitch" name="settings[telegram_enabled]"
                                       value="1" {{ $tgEnabled ? 'checked' : '' }}>
                            </div>
                            <span class="stg-switch-text">Telegram bildirimlerini etkinleştir</span>
                        </div>
                        <small class="stg-hint">Kapalıyken hiçbir Telegram mesajı gönderilmez. Bot Token ve Chat ID dolu olmalı.</small>
                    </div>
                </div>

                {{-- Bot Yapılandırması --}}
                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>Bot Yapılandırması</h6>
                        <p>BotFather'dan aldığın token ve hedef chat ID</p>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Bot Token</label>
                        <input type="password" class="stg-input" name="settings[telegram_bot_token]"
                               value="" placeholder="{{ $s('telegram_bot_token') ? '●●●●●●●● (değiştirmek için yeni token girin)' : '123456789:ABC-DEF1234ghIkl-zyx57W2v1u123ew11' }}"
                               autocomplete="new-password">
                        <small class="stg-hint">
                            <a href="https://t.me/BotFather" target="_blank" rel="noopener" class="text-teal">@BotFather</a> üzerinden <code>/newbot</code> komutu ile yeni bir bot oluşturup token alabilirsin. Boş bırakırsan mevcut değer korunur.
                        </small>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Chat ID</label>
                        <input type="text" class="stg-input" name="settings[telegram_chat_id]"
                               value="{{ $s('telegram_chat_id') }}" placeholder="123456789 veya -1001234567890">
                        <small class="stg-hint">
                            Bildirimi alacak kullanıcı/grup ID'si. Botla sohbet başlattıktan sonra
                            <code>https://api.telegram.org/bot&lt;TOKEN&gt;/getUpdates</code> adresinden öğrenebilirsin (<code>chat.id</code>).
                            Grup için <code>-100…</code> ile başlar.
                        </small>
                    </div>
                </div>

                {{-- Bildirim Seviyesi --}}
                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>Bildirim Seviyesi</h6>
                        <p>Hangi durumlarda Telegram'a mesaj gitsin?</p>
                    </div>

                    <div class="stg-field">
                        @php $tgLevel = $s('telegram_notify_level', 'permanent_only'); @endphp
                        <select class="stg-input" name="settings[telegram_notify_level]">
                            <option value="permanent_only" @selected($tgLevel === 'permanent_only')>
                                Sadece kalıcı hata (3/3 deneme sonunda) — önerilen
                            </option>
                            <option value="every_failure" @selected($tgLevel === 'every_failure')>
                                Her başarısızlıkta (1., 2., 3. denemede ayrı mesaj)
                            </option>
                        </select>
                        <small class="stg-hint">
                            <strong>Kalıcı hata:</strong> sadece tüm denemeler tükendiğinde tek mesaj gelir (sessiz, idempotent).<br>
                            <strong>Her başarısızlıkta:</strong> sorun başlar başlamaz haberin olur, ama 1 post için 3 mesaj gelebilir.
                        </small>
                    </div>
                </div>

                {{-- Bağlantı Testi --}}
                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>Bağlantı Testi</h6>
                        <p>Yapılandırmanın doğru çalıştığını anında doğrula</p>
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                        <button type="button" class="stg-btn" id="testTelegramBtn">
                            <i class="bi bi-send"></i> Test Mesajı Gönder
                        </button>
                    </div>
                    <small class="stg-hint mt-2 d-block">Önce ayarları <em>Kaydet</em>, ardından test mesajı gönder. Telegram'da mesaj göründüyse yapılandırma hazır.</small>
                </div>

                {{-- Kurulum Rehberi --}}
                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>Nasıl Bot Oluşturup Chat ID Alırım?</h6>
                        <p>Adım adım Telegram Bot kurulumu</p>
                    </div>
                    <div class="stg-hint" style="line-height:1.9">
                        <strong>1.</strong> Telegram'da <a href="https://t.me/BotFather" target="_blank" rel="noopener" class="text-teal">@BotFather</a> ile sohbet başlat.<br>
                        <strong>2.</strong> <code>/newbot</code> komutu gönder, bot ismi ve username belirle.<br>
                        <strong>3.</strong> BotFather'ın verdiği <em>HTTP API token</em>'ı yukarıdaki <strong>Bot Token</strong> alanına yapıştır.<br>
                        <strong>4.</strong> Yeni botunla bir mesaj alışverişi başlat (<code>/start</code> gönder) — yoksa bot sana mesaj gönderemez.<br>
                        <strong>5.</strong> Tarayıcıda <code>https://api.telegram.org/bot&lt;TOKEN&gt;/getUpdates</code> adresini aç, JSON içindeki <code>chat.id</code> değerini kopyala.<br>
                        <strong>6.</strong> Yukarıdaki <strong>Chat ID</strong> alanına yapıştır → <em>Kaydet</em> → <em>Test Mesajı Gönder</em>.<br>
                        <strong>İpucu:</strong> Birden çok kişi haber alacaksa bot'u bir Telegram grubuna ekle, grubun chat ID'sini kullan (<code>-100…</code> ile başlar).
                    </div>
                </div>

            </form>
        </div>

        {{-- ══════════════ AI İÇERİK ══════════════ --}}
        <div class="stg-panel" id="stg-ai">
            <form method="POST" action="{{ route('admin.settings.update') }}">
                @csrf
                @method('PUT')

                <div class="stg-panel-header">
                    <div>
                        <h5><i class="bi bi-robot"></i> AI İçerik Ayarları</h5>
                        <p>Gemini API ile otomatik blog içerik üretimi</p>
                    </div>
                    <button type="submit" class="stg-save-btn"><i class="bi bi-check-lg"></i> Kaydet</button>
                </div>

                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>API Anahtarı</h6>
                        <p>Google AI Studio üzerinden alınan Gemini API anahtarı</p>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label"><i class="bi bi-key me-2 text-warning"></i>Gemini API Key</label>
                        <input type="password" class="stg-input" name="settings[gemini_api_key]"
                               value="{{ $s('gemini_api_key') }}"
                               placeholder="{{ $s('gemini_api_key') ? '•••••••• (kayıtlı)' : 'AIzaSy...' }}"
                               autocomplete="new-password">
                        <small class="stg-hint">Boş bırakılırsa mevcut anahtar korunur. Cron ile günde 4 kez otomatik blog içeriği üretilir.</small>
                    </div>
                </div>

                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>Model Seçimi & Fallback</h6>
                        <p>Birincil model yoğunsa fallback modellerine otomatik geçer</p>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="stg-field">
                                <label class="stg-label"><i class="bi bi-cpu me-2 text-teal"></i>Birincil Model</label>
                                <input type="text" class="stg-input" name="settings[ai_primary_model]"
                                       value="{{ $s('ai_primary_model', 'gemini-2.5-flash') }}"
                                       placeholder="gemini-2.5-flash">
                                <small class="stg-hint">Önce denenen model. Önerilen: <code>gemini-2.5-flash</code></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stg-field">
                                <label class="stg-label"><i class="bi bi-arrow-repeat me-2 text-info"></i>Fallback Modeller</label>
                                <input type="text" class="stg-input" name="settings[ai_fallback_models]"
                                       value="{{ $s('ai_fallback_models', 'gemini-2.0-flash,gemini-2.5-flash-lite,gemini-2.0-flash-lite,gemini-1.5-flash') }}"
                                       placeholder="gemini-2.0-flash,gemini-2.5-flash-lite">
                                <small class="stg-hint">Virgülle ayırın. 503/429 hatalarında sırayla denenir.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6><i class="bi bi-newspaper me-2 text-warning"></i>Blog Otomatik Kapak Görseli</h6>
                        <p>Cron ve panelden üretilen blog yazılarına Görsel Kütüphanesi'nden otomatik kapak görseli seçimi</p>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">
                            <i class="bi bi-power me-2 text-success"></i>Otomatik Kapak Görseli Seçimi
                        </label>
                        <div class="form-check form-switch" style="padding-left:3em;">
                            <input type="hidden" name="settings[blog_auto_cover_image]" value="0">
                            <input class="form-check-input" type="checkbox"
                                   id="blogAutoCoverImage"
                                   name="settings[blog_auto_cover_image]"
                                   value="1"
                                   {{ (string) $s('blog_auto_cover_image', '1') === '1' ? 'checked' : '' }}
                                   style="width:3em;height:1.5em;">
                            <label class="form-check-label" for="blogAutoCoverImage" style="margin-left:8px;">
                                <span class="text-success">Aktif</span> — kapalıysa blog yazılarına placeholder görsel atanır
                            </label>
                        </div>
                        <small class="stg-hint">
                            Blog üretildiğinde Görsel Kütüphanesi'nden (<a href="{{ route('admin.media-library.index') }}" class="text-teal">Medya Kütüphanesi</a>)
                            ürün kategorisine göre otomatik kapak görseli seçilir. Eşleşme yoksa genel havuzdan, o da boşsa placeholder kullanılır.
                        </small>
                    </div>
                </div>

                {{-- ════════ BLOG OTOMATİK SOSYAL MEDYA PAYLAŞIMI ════════ --}}
                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6><i class="bi bi-share-fill me-2 text-info"></i>Blog Otomatik Sosyal Medya Paylaşımı</h6>
                        <p>Cron'dan üretilen blog yazıları otomatik olarak Instagram + Facebook'ta paylaşılsın mı?</p>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">
                            <i class="bi bi-instagram me-2 text-danger"></i>Instagram'a Otomatik Paylaş
                        </label>
                        <div class="form-check form-switch" style="padding-left:3em;">
                            <input type="hidden" name="settings[blog_auto_share_instagram]" value="0">
                            <input class="form-check-input" type="checkbox"
                                   id="blogAutoShareInstagram"
                                   name="settings[blog_auto_share_instagram]"
                                   value="1"
                                   {{ (string) $s('blog_auto_share_instagram', '1') === '1' ? 'checked' : '' }}
                                   style="width:3em;height:1.5em;">
                            <label class="form-check-label" for="blogAutoShareInstagram" style="margin-left:8px;">
                                <span class="text-success">Aktif</span> — kapalıysa cron blog yazısı için IG postu oluşturmaz
                            </label>
                        </div>
                        <small class="stg-hint">
                            Blog yazısı yayınlanınca kapak görseli + başlık + özet ile bir Instagram postu otomatik oluşur (Scheduled, ~5 dk sonra paylaşılır).
                            Placeholder görsel olan blog yazıları için paylaşım yapılmaz.
                        </small>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">
                            <i class="bi bi-facebook me-2 text-primary"></i>Facebook'a da Cross-Post
                        </label>
                        <div class="form-check form-switch" style="padding-left:3em;">
                            <input type="hidden" name="settings[blog_auto_share_facebook]" value="0">
                            <input class="form-check-input" type="checkbox"
                                   id="blogAutoShareFacebook"
                                   name="settings[blog_auto_share_facebook]"
                                   value="1"
                                   {{ (string) $s('blog_auto_share_facebook', '1') === '1' ? 'checked' : '' }}
                                   style="width:3em;height:1.5em;">
                            <label class="form-check-label" for="blogAutoShareFacebook" style="margin-left:8px;">
                                <span class="text-success">Aktif</span> — Facebook Page Token kuruluysa aynı içerik FB sayfasında da paylaşılır
                            </label>
                        </div>
                        <small class="stg-hint">
                            <strong>Not:</strong> Facebook paylaşımı için yukarıda Instagram bölümünde "Facebook Page ID" + "Page Access Token" tanımlı olmalı.
                            Token yoksa bu seçenek görmezden gelinir, sadece Instagram'a paylaşılır.
                        </small>
                    </div>
                </div>

                {{-- ════════ AI MALİYET BÜTÇE YÖNETİMİ ════════ --}}
                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6><i class="bi bi-cash-coin me-2 text-success"></i>AI Aylık Bütçe Yönetimi</h6>
                        <p>Görsel üretimi (Imagen / Gemini Image) maliyetleri kontrolü. Text üretimi (Gemini text) ücretsiz tier'da olduğu için bütçeye dahil değildir; rapor amaçlı görünür.</p>
                    </div>

                    @php
                        // Anlık bütçe durumunu Settings sayfasında da göster (kullanıcı limiti
                        // değiştirmeden önce mevcut harcamayı görsün)
                        try {
                            $budgetStatus = app(\App\Services\AiCostReportService::class)->budgetStatus();
                        } catch (\Throwable) {
                            $budgetStatus = null;
                        }
                    @endphp

                    @if($budgetStatus !== null)
                        <div class="alert alert-{{ $budgetStatus['state'] === 'exceeded' ? 'danger' : ($budgetStatus['state'] === 'warning' ? 'warning' : 'info') }} mb-3 small">
                            <strong><i class="bi bi-info-circle me-1"></i> Bu ayki harcama:</strong>
                            <span class="ms-2">
                                Görsel: <strong>${{ number_format($budgetStatus['used_image'], 2) }}</strong>
                                @if($budgetStatus['used_text'] > 0)
                                    + Text: ${{ number_format($budgetStatus['used_text'], 2) }}
                                @endif
                                / Limit ${{ number_format($budgetStatus['limit'], 2) }}
                                ({{ $budgetStatus['percent'] }}%)
                            </span>
                            @if($budgetStatus['exceeded'])
                                <strong class="text-danger d-block mt-1">⚠ Bütçe aşıldı!</strong>
                            @endif
                        </div>
                    @endif

                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="stg-field">
                                <label class="stg-label">
                                    <i class="bi bi-piggy-bank me-1 text-success"></i>Aylık Bütçe Limiti (USD)
                                </label>
                                <input type="number" min="1" max="10000" step="0.01" class="stg-input" name="settings[ai_monthly_budget_usd]"
                                       value="{{ $s('ai_monthly_budget_usd', '50') }}">
                                <small class="stg-hint">Görsel üretiminin aylık üst sınırı. Default $50. Aşılınca block aktifse yeni AI görsel üretilemez.</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stg-field">
                                <label class="stg-label">
                                    <i class="bi bi-exclamation-triangle me-1 text-warning"></i>Uyarı Eşiği (%)
                                </label>
                                <input type="number" min="50" max="100" step="1" class="stg-input" name="settings[ai_budget_alert_threshold]"
                                       value="{{ $s('ai_budget_alert_threshold', '80') }}">
                                <small class="stg-hint">Bu yüzdeye ulaşınca dashboard'da sarı uyarı. Default %80.</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stg-field">
                                <label class="stg-label">
                                    <i class="bi bi-shield-exclamation me-1 text-danger"></i>Bütçe Aşılınca AI Durdur
                                </label>
                                <div class="form-check form-switch" style="padding-left:3em;">
                                    <input type="hidden" name="settings[ai_budget_block_when_exceeded]" value="0">
                                    <input class="form-check-input" type="checkbox"
                                           id="aiBudgetBlock"
                                           name="settings[ai_budget_block_when_exceeded]"
                                           value="1"
                                           {{ (string) $s('ai_budget_block_when_exceeded', '1') === '1' ? 'checked' : '' }}
                                           style="width:3em;height:1.5em;">
                                    <label class="form-check-label" for="aiBudgetBlock" style="margin-left:8px;">
                                        <span class="text-success">Aktif</span>
                                    </label>
                                </div>
                                <small class="stg-hint">Aktifse: bütçe aşılınca yeni AI görsel çağrıları reddedilir. Cron blog → IG paylaşım fail olur (graceful — sadece görsel atlanır). Text gen etkilenmez.</small>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-secondary mt-3 small mb-0">
                        <i class="bi bi-bar-chart-line me-1"></i>
                        Detaylı maliyet raporu: <a href="{{ route('admin.ai-costs.index') }}" class="text-teal">/admin/ai-costs</a>
                    </div>
                </div>

                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>Retry & Timeout</h6>
                        <p>Geçici hatalara karşı dayanıklılık ayarları</p>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="stg-field">
                                <label class="stg-label">Maks. Deneme (model başına)</label>
                                <input type="number" min="1" max="10" class="stg-input" name="settings[ai_max_attempts]"
                                       value="{{ $s('ai_max_attempts', '5') }}">
                                <small class="stg-hint">Her model için retry sayısı (1-10). Varsayılan 5.</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stg-field">
                                <label class="stg-label">Timeout (saniye)</label>
                                <input type="number" min="10" max="300" class="stg-input" name="settings[ai_timeout_seconds]"
                                       value="{{ $s('ai_timeout_seconds', '90') }}">
                                <small class="stg-hint">Her HTTP isteği için zaman aşımı. Varsayılan 90.</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stg-field">
                                <label class="stg-label">İlk Backoff (saniye)</label>
                                <input type="number" min="1" max="30" class="stg-input" name="settings[ai_initial_backoff]"
                                       value="{{ $s('ai_initial_backoff', '4') }}">
                                <small class="stg-hint">İlk retry gecikmesi. Sonraki denemelerde 2× artar, ±%25 jitter eklenir.</small>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <div class="stg-field">
                                <label class="stg-label"><i class="bi bi-hourglass-split me-2 text-warning"></i>Toplam Süre Bütçesi (saniye)</label>
                                <input type="number" min="30" max="600" class="stg-input" name="settings[ai_total_budget_seconds]"
                                       value="{{ $s('ai_total_budget_seconds', '240') }}">
                                <small class="stg-hint">Tüm modeller ve retry'lar dahil üst süre sınırı. Bu süreyi aşmamak için yeni denemeler iptal edilir. Varsayılan 240s (4 dakika).</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>Önerilen Gemini Modelleri</h6>
                        <p>Hepsi Google AI Studio'da aynı API anahtarı ile çalışır</p>
                    </div>
                    <div class="stg-hint" style="line-height:1.9">
                        <code>gemini-2.5-flash</code> — En güncel, en yoğun<br>
                        <code>gemini-2.0-flash</code> — Hızlı, dengeli<br>
                        <code>gemini-2.5-flash-lite</code> — Hafif, düşük maliyet<br>
                        <code>gemini-2.0-flash-lite</code> — Hafif, düşük maliyet<br>
                        <code>gemini-1.5-flash</code> — Stabil, geniş uygunluk
                    </div>
                </div>

                {{-- ═══ AI GÖRSEL ÜRETİMİ (tüm sistem için tek kaynak) ═══ --}}
                <div class="stg-section" style="border-top:2px solid rgba(255,255,255,0.08); padding-top:28px; margin-top:28px;">
                    <div class="stg-section-title">
                        <h6><i class="bi bi-image me-2 text-info"></i>AI Görsel Üretimi</h6>
                        <p>
                            Text üretiminden <strong>tamamen ayrı</strong> — kendi API key'i ve kendi aktif/pasif toggle'ı var.
                            Bu bölüm <strong>sistemdeki TÜM AI görsel üretimini</strong> kontrol eder:
                            <code>/admin/ai-images</code>, Instagram post oluşturma, blog otomatik kapak görseli, ürün foto iyileştirme, bulk import.
                        </p>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">
                            <i class="bi bi-power me-2 text-success"></i>Görsel Üretimi (Global)
                        </label>
                        <div class="form-check form-switch" style="padding-left:3em;">
                            <input type="hidden" name="settings[ai_image_enabled]" value="0">
                            <input class="form-check-input" type="checkbox"
                                   id="aiImageEnabled"
                                   name="settings[ai_image_enabled]"
                                   value="1"
                                   {{ (string) $s('ai_image_enabled', '0') === '1' ? 'checked' : '' }}
                                   style="width:3em;height:1.5em;">
                            <label class="form-check-label" for="aiImageEnabled" style="margin-left:8px;">
                                <span class="text-success">Aktif</span> — kapatırsan sistemdeki <strong>HİÇBİR yerden</strong> AI görsel üretilemez
                                (ai-images, instagram-posts, blog kapak, ürün enhance dahil).
                            </label>
                        </div>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label"><i class="bi bi-key-fill me-2 text-warning"></i>AI Görsel API Key</label>
                        <input type="password" class="stg-input" name="settings[ai_image_api_key]"
                               value="{{ $s('ai_image_api_key') }}"
                               placeholder="{{ $s('ai_image_api_key') ? '•••••••• (kayıtlı)' : 'AIzaSy...' }}"
                               autocomplete="new-password">
                        <small class="stg-hint">
                            <strong>Text API key'inden farklı, ayrı bir key girin.</strong>
                            Görsel üretim billing gerektirir (~$0.04/görsel),
                            bu key sadece görsel işlemi için kullanılır. Silersen tüm AI görsel üretimi durur,
                            <code>gemini_api_key</code> üzerinden yürüyen text/blog/ürün üretimi etkilenmez.
                        </small>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="stg-field">
                                <label class="stg-label"><i class="bi bi-cpu me-2 text-teal"></i>Birincil Görsel Modeli</label>
                                <input type="text" class="stg-input" name="settings[ai_image_model]"
                                       value="{{ $s('ai_image_model', 'gemini-3.1-flash-image-preview') }}"
                                       placeholder="gemini-3.1-flash-image-preview">
                                <small class="stg-hint">Önerilen: <code>gemini-3.1-flash-image-preview</code> (Nano Banana 2 — en yeni ~$0.07/görsel).</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stg-field">
                                <label class="stg-label"><i class="bi bi-arrow-repeat me-2 text-info"></i>Fallback Görsel Modelleri</label>
                                <input type="text" class="stg-input" name="settings[ai_image_fallback_models]"
                                       value="{{ $s('ai_image_fallback_models', 'gemini-3-pro-image-preview,gemini-2.5-flash-image,gemini-2.5-flash-image-preview') }}"
                                       placeholder="gemini-3-pro-image-preview,gemini-2.5-flash-image,...">
                                <small class="stg-hint">
                                    Virgülle ayırın. Birincil model 404/400 dönerse sırayla denenir.
                                    <strong>Sadece var olan modelleri ekleyin</strong> — yoksa "Tüm modeller başarısız" hatası alırsınız.
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="stg-hint mt-3" style="line-height:1.9; background:rgba(13,202,240,0.08); padding:12px 16px; border-radius:8px; border-left:3px solid #0dcaf0;">
                        <strong>Önerilen Sıralama (1. tercih → fallback):</strong><br>
                        <code>gemini-3.1-flash-image-preview</code> — <strong>Nano Banana 2</strong> (varsayılan birincil, ~$0.07)<br>
                        <code>gemini-3-pro-image-preview</code> — <strong>Nano Banana Pro</strong> (en kaliteli, ~$0.10)<br>
                        <code>gemini-2.5-flash-image</code> — Nano Banana (GA, ucuz fallback ~$0.04)<br>
                        <em class="text-muted">Not: Maliyet, kullanılan model ve görsel ölçüsü her üretimde <code>ai_generated_images</code> tablosuna loglanır. Eski ayar değerlerini bu yeni öneriye güncellemek için input alanını boşaltıp Kaydet diyebilirsiniz (varsayılan otomatik uygulanır).</em>
                    </div>
                </div>

                {{-- ─── Google Vertex AI (Ayrı Modül) ─── --}}
                <div class="stg-section" id="stg-vertex">
                    <div class="stg-panel-header">
                        <h5><i class="bi bi-stars me-2 text-teal"></i>Google Vertex AI (Nano Banana)</h5>
                        <p>
                            Vertex AI tabanlı görsel üretim modülü. <strong>Diğer Gemini key'lerinden TAMAMEN AYRI</strong> —
                            kendi API key'i, kendi endpoint'i, kendi modeli vardır.
                        </p>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label"><i class="bi bi-key-fill me-2 text-warning"></i>Vertex API Key</label>
                        <input type="password" class="stg-input" name="settings[vertex_api_key]"
                               value="{{ $s('vertex_api_key') }}"
                               placeholder="{{ $s('vertex_api_key') ? '•••••••• (kayıtlı)' : 'AQ.Ab...' }}"
                               autocomplete="new-password">
                        <small class="stg-hint">
                            <strong>Vertex AI Express Mode API key'i.</strong>
                            <code>aiplatform.googleapis.com</code> endpoint'ine <code>?key=</code> query param olarak gönderilir.
                            Diğer Gemini key'lerinden bağımsız. Silersen Vertex modülü çalışmaz, mevcut AI Görsel modülü etkilenmez.
                        </small>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="stg-field">
                                <label class="stg-label"><i class="bi bi-globe me-2 text-info"></i>Endpoint</label>
                                <input type="text" class="stg-input" name="settings[vertex_endpoint]"
                                       value="{{ $s('vertex_endpoint', 'aiplatform.googleapis.com') }}"
                                       placeholder="aiplatform.googleapis.com">
                                <small class="stg-hint">Vertex AI host (varsayılan: <code>aiplatform.googleapis.com</code>).</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stg-field">
                                <label class="stg-label"><i class="bi bi-cpu me-2 text-teal"></i>Model</label>
                                <input type="text" class="stg-input" name="settings[vertex_model]"
                                       value="{{ $s('vertex_model', 'gemini-3.1-flash-image-preview') }}"
                                       placeholder="gemini-3.1-flash-image-preview">
                                <small class="stg-hint">Vertex AI image generation modeli (<code>:streamGenerateContent</code>).</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stg-field">
                                <label class="stg-label"><i class="bi bi-stopwatch me-2 text-warning"></i>Timeout (saniye)</label>
                                <input type="number" min="30" max="600" class="stg-input" name="settings[vertex_timeout]"
                                       value="{{ $s('vertex_timeout', '180') }}"
                                       placeholder="180">
                                <small class="stg-hint">API isteği timeout süresi (30-600 sn). Görsel üretim 60 sn'i aşabilir, en az 120 öneririz.</small>
                            </div>
                        </div>
                    </div>

                    <div class="stg-hint mt-3" style="line-height:1.9; background:rgba(20,184,166,0.08); padding:12px 16px; border-radius:8px; border-left:3px solid #14b8a6;">
                        <strong>Vertex modülü için:</strong> Admin → <a href="{{ route('admin.vertex.index') }}" class="text-teal">Google Vertex → Vertex Görsel Üret</a>.
                        Şablonlar kendi <code>generation_config</code>'unu taşır (aspect_ratio, image_size, temperature, top_p, thinking_level, person_generation, safety_off).
                    </div>
                </div>
            </form>
        </div>

        {{-- ══════════════ 12. BÖLGESEL AYARLAR ══════════════ --}}
        <div class="stg-panel" id="stg-regional">
            <form method="POST" action="{{ route('admin.settings.update') }}">
                @csrf
                @method('PUT')

                <div class="stg-section">
                    <div class="stg-panel-header">
                        <h5><i class="bi bi-globe2 me-2 text-teal"></i>Bölgesel Ayarlar</h5>
                        <p>Dil ve saat dilimi tercihleri</p>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="stg-field">
                                <label class="stg-label">Dil</label>
                                <select class="stg-select" name="settings[app_locale]">
                                    <option value="tr" {{ $s('app_locale', 'tr') === 'tr' ? 'selected' : '' }}>Türkçe</option>
                                    <option value="en" {{ $s('app_locale', 'tr') === 'en' ? 'selected' : '' }}>English</option>
                                </select>
                                <span class="stg-hint">Uygulamanın arayüz dili</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stg-field">
                                <label class="stg-label">Saat Dilimi</label>
                                <select class="stg-select" name="settings[app_timezone]">
                                    @php
                                        $currentTz = $s('app_timezone', 'Europe/Istanbul');
                                        $timezones = [
                                            'Europe/Istanbul'  => 'Europe/Istanbul (UTC+3)',
                                            'Europe/London'    => 'Europe/London (UTC+0)',
                                            'Europe/Berlin'    => 'Europe/Berlin (UTC+1)',
                                            'Europe/Moscow'    => 'Europe/Moscow (UTC+3)',
                                            'America/New_York' => 'America/New_York (UTC-5)',
                                            'America/Chicago'  => 'America/Chicago (UTC-6)',
                                            'America/Denver'   => 'America/Denver (UTC-7)',
                                            'America/Los_Angeles' => 'America/Los_Angeles (UTC-8)',
                                            'Asia/Dubai'       => 'Asia/Dubai (UTC+4)',
                                            'Asia/Kolkata'     => 'Asia/Kolkata (UTC+5:30)',
                                            'Asia/Shanghai'    => 'Asia/Shanghai (UTC+8)',
                                            'Asia/Tokyo'       => 'Asia/Tokyo (UTC+9)',
                                            'Australia/Sydney' => 'Australia/Sydney (UTC+11)',
                                            'Pacific/Auckland' => 'Pacific/Auckland (UTC+12)',
                                        ];
                                    @endphp
                                    @foreach($timezones as $tz => $label)
                                        <option value="{{ $tz }}" {{ $currentTz === $tz ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <span class="stg-hint">Tarih ve saat gösteriminde kullanılacak saat dilimi</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="stg-form-actions">
                    <button type="submit" class="btn-teal">
                        <i class="bi bi-check-lg me-1"></i> Kaydet
                    </button>
                </div>
            </form>
        </div>

        {{-- ══════════════ 13. SİSTEM ══════════════ --}}
        <div class="stg-panel" id="stg-system">

            {{-- Sistem Durumu --}}
            <div class="stg-section">
                <div class="stg-section-title">
                    <h6>Sistem Durumu</h6>
                </div>

                <div class="stg-system-status">
                    <div class="stg-status-item">
                        <div class="stg-status-left">
                            <span class="stg-status-dot stg-dot-ok"></span>
                            <span class="stg-status-label">Web Sunucusu</span>
                        </div>
                        <span class="stg-status-value">Çalışıyor</span>
                    </div>
                    <div class="stg-status-item">
                        <div class="stg-status-left">
                            <span class="stg-status-dot {{ $systemInfo['db_connected'] ? 'stg-dot-ok' : 'stg-dot-danger' }}"></span>
                            <span class="stg-status-label">Veritabanı (MySQL)</span>
                        </div>
                        <span class="stg-status-value">{{ $systemInfo['db_connected'] ? 'Bağlı' : 'Bağlantı Yok' }}</span>
                    </div>
                    <div class="stg-status-item">
                        <div class="stg-status-left">
                            <span class="stg-status-dot stg-dot-ok"></span>
                            <span class="stg-status-label">PHP Sürümü</span>
                        </div>
                        <span class="stg-status-value">{{ $systemInfo['php_version'] }}</span>
                    </div>
                    <div class="stg-status-item">
                        <div class="stg-status-left">
                            <span class="stg-status-dot stg-dot-ok"></span>
                            <span class="stg-status-label">Laravel Sürümü</span>
                        </div>
                        <span class="stg-status-value">{{ $systemInfo['laravel_version'] }}</span>
                    </div>
                </div>

                @php
                    // PHP-FPM web ayarları (CLI'dan farklı olabilir)
                    $parseSize = function (string $val): int {
                        $val = trim($val);
                        if ($val === '' || $val === '0') return 0;
                        $unit = strtolower(substr($val, -1));
                        $num = (int) $val;
                        return match($unit) {
                            'g' => $num * 1024 * 1024 * 1024,
                            'm' => $num * 1024 * 1024,
                            'k' => $num * 1024,
                            default => $num,
                        };
                    };
                    $upload  = $parseSize($systemInfo['php_upload_max_filesize'] ?? '0');
                    $post    = $parseSize($systemInfo['php_post_max_size'] ?? '0');
                    $memory  = $parseSize($systemInfo['php_memory_limit'] ?? '0');

                    // Önerilen minimum: video upload için 128MB
                    $uploadOk = $upload >= 100 * 1024 * 1024;
                    $postOk   = $post >= 100 * 1024 * 1024;
                    $memoryOk = $memory >= 256 * 1024 * 1024 || $memory === 0;
                @endphp

                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6><i class="bi bi-cloud-upload"></i> PHP Web Yükleme Limitleri ({{ $systemInfo['php_sapi'] ?? '?' }})</h6>
                        <p>Web isteklerinde geçerli PHP ayarları. Reels videoları için 128M+ önerilir.</p>
                    </div>

                    <div class="stg-system-status">
                        <div class="stg-status-item">
                            <div class="stg-status-left">
                                <span class="stg-status-dot {{ $uploadOk ? 'stg-dot-ok' : 'stg-dot-danger' }}"></span>
                                <span class="stg-status-label">upload_max_filesize</span>
                            </div>
                            <span class="stg-status-value">
                                {{ $systemInfo['php_upload_max_filesize'] }}
                                @if(! $uploadOk) <small class="text-danger ms-1">(düşük — 128M önerilir)</small> @endif
                            </span>
                        </div>
                        <div class="stg-status-item">
                            <div class="stg-status-left">
                                <span class="stg-status-dot {{ $postOk ? 'stg-dot-ok' : 'stg-dot-danger' }}"></span>
                                <span class="stg-status-label">post_max_size</span>
                            </div>
                            <span class="stg-status-value">
                                {{ $systemInfo['php_post_max_size'] }}
                                @if(! $postOk) <small class="text-danger ms-1">(düşük — 128M önerilir)</small> @endif
                            </span>
                        </div>
                        <div class="stg-status-item">
                            <div class="stg-status-left">
                                <span class="stg-status-dot {{ $memoryOk ? 'stg-dot-ok' : 'stg-dot-warn' }}"></span>
                                <span class="stg-status-label">memory_limit</span>
                            </div>
                            <span class="stg-status-value">{{ $systemInfo['php_memory_limit'] }}</span>
                        </div>
                        <div class="stg-status-item">
                            <div class="stg-status-left">
                                <span class="stg-status-dot stg-dot-ok"></span>
                                <span class="stg-status-label">max_execution_time</span>
                            </div>
                            <span class="stg-status-value">
                                {{ $systemInfo['php_max_execution_time'] }}{{ $systemInfo['php_max_execution_time'] !== '0' ? ' sn' : ' (sınırsız)' }}
                            </span>
                        </div>
                    </div>

                    @if(! $uploadOk || ! $postOk)
                    <div class="alert alert-warning mt-3 mb-0 small">
                        <i class="bi bi-info-circle me-1"></i>
                        <strong>Düzeltme:</strong> Web FPM ayarları düşük. PHP-FPM config dosyasında
                        <code>upload_max_filesize=128M</code>, <code>post_max_size=128M</code> ayarla
                        ve <code>php-fpm</code> servisini yeniden başlat.
                        CLI ayarları (<code>php -i</code>) web ile aynı olmayabilir.
                    </div>
                    @endif
                </div>
            </div>

            {{-- Tehlikeli Bölge --}}
            <div class="stg-section stg-danger-zone">
                <div class="stg-section-title">
                    <h6><i class="bi bi-exclamation-triangle"></i> Tehlikeli Bölge</h6>
                    <p>Bu işlemler dikkatli kullanılmalıdır</p>
                </div>

                <div class="stg-danger-item">
                    <div class="stg-danger-info">
                        <strong>Önbelleği Temizle</strong>
                        <small>Tüm uygulama önbelleğini temizle</small>
                    </div>
                    <button type="button" class="stg-btn-danger" id="clearCacheBtn">
                        <i class="bi bi-trash3"></i> Temizle
                    </button>
                </div>
            </div>
        </div>

    </div>{{-- stg-content --}}
</div>{{-- stg-layout --}}
@endsection

@push('scripts')
<script>
(function() {
    'use strict';

    // Tab switching
    window.switchSettingsTab = function(el, panelId) {
        document.querySelectorAll('.stg-nav-item').forEach(function(n) { n.classList.remove('active'); });
        document.querySelectorAll('.stg-panel').forEach(function(p) { p.classList.remove('active'); });
        el.classList.add('active');
        document.getElementById(panelId).classList.add('active');
        document.querySelector('.stg-content').scrollTop = 0;
        sessionStorage.setItem('stg-active-tab', panelId);
    };

    // Restore last active tab
    var lastTab = sessionStorage.getItem('stg-active-tab');
    if (lastTab) {
        var navItem = document.querySelector('.stg-nav-item[href="#' + lastTab + '"]');
        if (navItem) {
            switchSettingsTab(navItem, lastTab);
        }
    }

    // File input preview
    function previewFile(e, imgId, defaultId) {
        var file = e.target.files[0];
        if (!file) return;
        if (file.size > 1 * 1024 * 1024) {
            AdminModal.status({
              title: 'Dosya Hatası',
              message: 'Dosya boyutu 1 MB\'dan büyük olamaz',
              type: 'warning'
            });
            e.target.value = '';
            return;
        }
        var reader = new FileReader();
        reader.onload = function(ev) {
            var img = document.getElementById(imgId);
            if (img) {
                img.src = ev.target.result;
                img.classList.remove('d-none');
            }
            if (defaultId) {
                var def = document.getElementById(defaultId);
                if (def) def.classList.add('d-none');
            }
        };
        reader.readAsDataURL(file);
    }

    var logoInput = document.getElementById('logoFileInput');
    if (logoInput) {
        logoInput.addEventListener('change', function(e) { previewFile(e, 'logoPreviewImg', 'logoDefault'); });
    }

    var faviconInput = document.getElementById('faviconFileInput');
    if (faviconInput) {
        faviconInput.addEventListener('change', function(e) { previewFile(e, 'faviconPreviewImg', 'faviconDefault'); });
    }

    var ogInput = document.getElementById('ogImageInput');
    if (ogInput) {
        ogInput.addEventListener('change', function(e) { previewFile(e, 'ogImagePreview', null); });
    }

    // Mail logo preview
    var mailLogoInput = document.getElementById('mailLogoFileInput');
    if (mailLogoInput) {
        mailLogoInput.addEventListener('change', function(e) { previewFile(e, 'mailLogoPreviewImg', 'mailLogoDefault'); });
    }

    // Mail logo remove
    var mailLogoRemoveBtn = document.getElementById('mailLogoRemoveBtn');
    if (mailLogoRemoveBtn) {
        mailLogoRemoveBtn.addEventListener('click', function() {
            document.getElementById('mailLogoRemoveInput').value = '1';
            var img = document.getElementById('mailLogoPreviewImg');
            if (img) { img.classList.add('d-none'); img.src = ''; }
            var def = document.getElementById('mailLogoDefault');
            if (def) def.classList.remove('d-none');
            mailLogoRemoveBtn.classList.add('d-none');
        });
    }

    // Mail mode card switching
    document.querySelectorAll('.stg-mode-card input[type="radio"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            document.querySelectorAll('.stg-mode-card').forEach(function(c) { c.classList.remove('active'); });
            radio.closest('.stg-mode-card').classList.add('active');
            var testField = document.getElementById('mailTestAddressesField');
            if (testField) {
                testField.classList.toggle('d-none', radio.value !== 'developer');
            }
        });
    });

    // Test email
    var testBtn = document.getElementById('sendTestEmailBtn');
    if (testBtn) {
        testBtn.addEventListener('click', function() {
            var email = (document.getElementById('testEmailInput').value || '').trim();
            var subject = (document.getElementById('testEmailSubject').value || '').trim();
            var message = (document.getElementById('testEmailMessage').value || '').trim();
            if (!email) {
                AdminModal.status({ title: 'Uyarı', message: 'Lütfen bir alıcı e-posta adresi girin', type: 'warning' });
                return;
            }
            if (!subject) {
                AdminModal.status({ title: 'Uyarı', message: 'Lütfen bir konu girin', type: 'warning' });
                return;
            }
            testBtn.disabled = true;
            testBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Gönderiliyor...';
            fetch('{{ route("admin.settings.test-email") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ email: email, subject: subject, message: message })
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                AdminModal.status({
                    title: data.success ? 'Başarılı' : 'Hata',
                    message: data.message,
                    type: data.success ? 'success' : 'danger'
                });
            })
            .catch(function() {
                AdminModal.status({ title: 'Hata', message: 'Test e-postası gönderilemedi', type: 'danger' });
            })
            .finally(function() {
                testBtn.disabled = false;
                testBtn.innerHTML = '<i class="bi bi-send"></i> Test Maili Gönder';
            });
        });
    }

    // SEO Google preview - live update
    var seoTitleInput = document.querySelector('input[name="settings[seo_home_title]"]');
    var seoDescInput = document.querySelector('textarea[name="settings[seo_home_description]"]');
    var seoPreviewTitle = document.getElementById('seoPreviewTitle');
    var seoPreviewDesc = document.getElementById('seoPreviewDesc');

    if (seoTitleInput && seoPreviewTitle) {
        seoTitleInput.addEventListener('input', function() {
            seoPreviewTitle.textContent = this.value || '{{ config('app.name', 'Site Başlığı') }}';
        });
    }
    if (seoDescInput && seoPreviewDesc) {
        seoDescInput.addEventListener('input', function() {
            seoPreviewDesc.textContent = this.value || 'Arama sonuçlarında görünecek açıklama buraya gelecek.';
        });
    }

    // Checkbox hidden inputs for unchecked state
    document.querySelectorAll('.stg-switch input[type="checkbox"]').forEach(function(cb) {
        if (!cb.name) return;
        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = cb.name;
        hidden.value = '0';
        cb.parentElement.insertBefore(hidden, cb);
    });

    // ── Mail Theme: Color picker sync & live preview ──
    function syncColorHex(pickerId, hexId) {
        var picker = document.getElementById(pickerId);
        var hex = document.getElementById(hexId);
        if (!picker || !hex) return;
        picker.addEventListener('input', function() {
            hex.value = picker.value;
            updateMailPreview();
        });
    }
    syncColorHex('mtPrimaryColor', 'mtPrimaryColorHex');
    syncColorHex('mtPrimaryDarkColor', 'mtPrimaryDarkColorHex');
    syncColorHex('mtBgColor', 'mtBgColorHex');
    syncColorHex('mtCardBgColor', 'mtCardBgColorHex');
    syncColorHex('mtTextColor', 'mtTextColorHex');
    syncColorHex('mtMutedColor', 'mtMutedColorHex');

    function updateMailPreview() {
        var primary = (document.getElementById('mtPrimaryColor') || {}).value || '#4a7c43';
        var primaryDark = (document.getElementById('mtPrimaryDarkColor') || {}).value || '#2d5a27';
        var bg = (document.getElementById('mtBgColor') || {}).value || '#f0f4e8';
        var cardBg = (document.getElementById('mtCardBgColor') || {}).value || '#ffffff';
        var text = (document.getElementById('mtTextColor') || {}).value || '#4a4a4a';
        var muted = (document.getElementById('mtMutedColor') || {}).value || '#888888';

        var wrap = document.getElementById('mailThemePreviewWrap');
        var header = document.getElementById('mpHeader');
        var content = document.getElementById('mpContent');
        var accent = document.getElementById('mpAccent');
        var footer = document.getElementById('mpFooter');
        var heading = document.getElementById('mpHeading');
        var greeting = document.getElementById('mpGreeting');
        var mpText = document.getElementById('mpText');
        var mpMuted = document.getElementById('mpMuted');
        var btn = document.getElementById('mpBtn');

        if (wrap) wrap.style.backgroundColor = bg;
        if (header) header.style.background = 'linear-gradient(135deg, ' + primaryDark + ' 0%, ' + primary + ' 50%, ' + primaryDark + ' 100%)';
        if (content) content.style.backgroundColor = cardBg;
        if (accent) accent.style.background = 'linear-gradient(90deg, #d4a84b, ' + primary + ', #d4a84b)';
        if (footer) footer.style.backgroundColor = primaryDark;
        if (heading) heading.style.color = text;
        if (greeting) greeting.style.color = primary;
        if (mpText) mpText.style.color = text;
        if (mpMuted) mpMuted.style.color = muted;
        if (btn) btn.style.background = 'linear-gradient(135deg, ' + primary + ', ' + primaryDark + ')';
    }

    // Footer text live update
    var footerTextInput = document.getElementById('mtFooterText');
    if (footerTextInput) {
        footerTextInput.addEventListener('input', function() {
            var el = document.getElementById('mpFooterText');
            if (el) el.textContent = this.value || 'Çiftliğimizden sofranıza, doğallığın en taze hali.';
        });
    }

    // Social links toggle
    var socialToggle = document.getElementById('mtSocialLinks');
    if (socialToggle) {
        socialToggle.addEventListener('change', function() {
            var el = document.getElementById('mpSocial');
            if (el) el.style.display = this.checked ? 'flex' : 'none';
        });
    }

    // Initial preview render
    updateMailPreview();
    if (socialToggle) {
        var socialEl = document.getElementById('mpSocial');
        if (socialEl) socialEl.style.display = socialToggle.checked ? 'flex' : 'none';
    }

    // Google API test
    var testGoogleBtn = document.getElementById('testGoogleApiBtn');
    if (testGoogleBtn) {
        testGoogleBtn.addEventListener('click', function() {
            testGoogleBtn.disabled = true;
            testGoogleBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Test ediliyor...';
            var resultDiv = document.getElementById('googleApiTestResult');
            fetch('{{ route("admin.settings.test-google-api") }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                AdminModal.status({
                    title: data.success ? 'Başarılı' : 'Hata',
                    message: data.message,
                    type: data.success ? 'success' : 'danger'
                });
            })
            .catch(function() {
                AdminModal.status({ title: 'Hata', message: 'API testi başarısız oldu.', type: 'danger' });
            })
            .finally(function() {
                testGoogleBtn.disabled = false;
                testGoogleBtn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Bağlantıyı Test Et';
            });
        });
    }

    // YouTube API test
    var testYoutubeBtn = document.getElementById('testYoutubeApiBtn');
    if (testYoutubeBtn) {
        testYoutubeBtn.addEventListener('click', function() {
            testYoutubeBtn.disabled = true;
            testYoutubeBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Test ediliyor...';
            fetch('{{ route("admin.settings.test-youtube-api") }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                AdminModal.status({
                    title: data.success ? 'Başarılı' : 'Hata',
                    message: data.message,
                    type: data.success ? 'success' : 'danger'
                });
            })
            .catch(function() {
                AdminModal.status({ title: 'Hata', message: 'YouTube API testi başarısız oldu.', type: 'danger' });
            })
            .finally(function() {
                testYoutubeBtn.disabled = false;
                testYoutubeBtn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Bağlantıyı Test Et';
            });
        });
    }

    // Telegram test mesajı
    var testTelegramBtn = document.getElementById('testTelegramBtn');
    if (testTelegramBtn) {
        var telegramBtnDefault = testTelegramBtn.innerHTML;
        testTelegramBtn.addEventListener('click', function() {
            testTelegramBtn.disabled = true;
            testTelegramBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Gönderiliyor...';
            fetch('{{ route("admin.settings.test-telegram") }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                AdminModal.status({
                    title: data.success ? 'Başarılı' : 'Hata',
                    message: data.message,
                    type: data.success ? 'success' : 'danger'
                });
            })
            .catch(function() {
                AdminModal.status({ title: 'Hata', message: 'Telegram test isteği başarısız oldu.', type: 'danger' });
            })
            .finally(function() {
                testTelegramBtn.disabled = false;
                testTelegramBtn.innerHTML = telegramBtnDefault;
            });
        });
    }

    // Instagram API test
    var testInstagramBtn = document.getElementById('testInstagramApiBtn');
    if (testInstagramBtn) {
        testInstagramBtn.addEventListener('click', function() {
            testInstagramBtn.disabled = true;
            testInstagramBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Test ediliyor...';
            fetch('{{ route("admin.settings.test-instagram") }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                AdminModal.status({
                    title: data.success ? 'Başarılı' : 'Hata',
                    message: data.message,
                    type: data.success ? 'success' : 'danger'
                });
            })
            .catch(function() {
                AdminModal.status({ title: 'Hata', message: 'Instagram API testi başarısız oldu.', type: 'danger' });
            })
            .finally(function() {
                testInstagramBtn.disabled = false;
                testInstagramBtn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Bağlantıyı Test Et';
            });
        });
    }

    // Instagram token refresh
    var refreshInstaTokenBtn = document.getElementById('refreshInstagramTokenBtn');
    if (refreshInstaTokenBtn) {
        refreshInstaTokenBtn.addEventListener('click', function() {
            AdminModal.confirm({
                title: 'Token Yenileme',
                message: 'Mevcut Instagram access token\'ı 60 gün daha uzatılacak. Devam edilsin mi?',
                type: 'warning',
                confirmText: 'Evet, Yenile',
                confirmIcon: 'bi bi-shield-lock'
            }).then(function(ok) {
                if (!ok) return;
                refreshInstaTokenBtn.disabled = true;
                refreshInstaTokenBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Yenileniyor...';
                fetch('{{ route("admin.settings.refresh-instagram-token") }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    AdminModal.status({
                        title: data.success ? 'Başarılı' : 'Hata',
                        message: data.message,
                        type: data.success ? 'success' : 'danger'
                    });
                })
                .catch(function() {
                    AdminModal.status({ title: 'Hata', message: 'Token yenilenemedi.', type: 'danger' });
                })
                .finally(function() {
                    refreshInstaTokenBtn.disabled = false;
                    refreshInstaTokenBtn.innerHTML = '<i class="bi bi-shield-lock"></i> Token\'ı Yenile (60 gün uzat)';
                });
            });
        });
    }

    // Facebook page token fetch
    var fetchFbTokenBtn = document.getElementById('fetchFacebookPageTokenBtn');
    if (fetchFbTokenBtn) {
        fetchFbTokenBtn.addEventListener('click', function() {
            fetchFbTokenBtn.disabled = true;
            fetchFbTokenBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Alınıyor...';
            fetch('{{ route("admin.settings.fetch-facebook-page-token") }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                AdminModal.status({
                    title: data.success ? 'Başarılı' : 'Hata',
                    message: data.message,
                    type: data.success ? 'success' : 'danger'
                });
                if (data.success) setTimeout(function() { location.reload(); }, 1500);
            })
            .catch(function() {
                AdminModal.status({ title: 'Hata', message: 'Page token alınamadı.', type: 'danger' });
            })
            .finally(function() {
                fetchFbTokenBtn.disabled = false;
                fetchFbTokenBtn.innerHTML = '<i class="bi bi-key-fill"></i> Page Token\'ı Otomatik Al';
            });
        });
    }

    // Facebook API test
    var testFbBtn = document.getElementById('testFacebookApiBtn');
    if (testFbBtn) {
        testFbBtn.addEventListener('click', function() {
            testFbBtn.disabled = true;
            testFbBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Test ediliyor...';
            fetch('{{ route("admin.settings.test-facebook") }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                AdminModal.status({
                    title: data.success ? 'Başarılı' : 'Hata',
                    message: data.message,
                    type: data.success ? 'success' : 'danger'
                });
            })
            .catch(function() {
                AdminModal.status({ title: 'Hata', message: 'Facebook API testi başarısız.', type: 'danger' });
            })
            .finally(function() {
                testFbBtn.disabled = false;
                testFbBtn.innerHTML = '<i class="bi bi-facebook"></i> Facebook Bağlantısını Test Et';
            });
        });
    }

    // Clear cache
    var clearCacheBtn = document.getElementById('clearCacheBtn');
    if (clearCacheBtn) {
        clearCacheBtn.addEventListener('click', function() {
            AdminModal.confirm({
                title: 'Önbellek Temizleme',
                message: 'Tüm uygulama önbelleğini temizlemek istediğinizden emin misiniz?',
                type: 'warning',
                confirmText: 'Evet, Temizle',
                confirmIcon: 'bi bi-trash3'
            }).then(function(confirmed) {
                if (!confirmed) return;
                clearCacheBtn.disabled = true;
                clearCacheBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Temizleniyor...';
                fetch('{{ route("admin.settings.clear-cache") }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    AdminModal.status({
                        title: data.success ? 'Başarılı' : 'Hata',
                        message: data.message,
                        type: data.success ? 'success' : 'danger'
                    });
                })
                .catch(function() {
                    AdminModal.status({ title: 'Hata', message: 'Önbellek temizlenemedi.', type: 'danger' });
                })
                .finally(function() {
                    clearCacheBtn.disabled = false;
                    clearCacheBtn.innerHTML = '<i class="bi bi-trash3"></i> Temizle';
                });
            });
        });
    }
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/glightbox@3.3.0/dist/js/glightbox.min.js"></script>
<script>
    var lightbox = GLightbox({ selector: '.glightbox', touchNavigation: true, loop: true });

    // ── TikTok Audit Rehberi: kopyala butonları ──────────────────
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.stg-copy-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var targetId = btn.dataset.copyTarget;
                var target = document.getElementById(targetId);
                if (! target) return;

                var text = target.value !== undefined ? target.value : target.textContent;

                var doneSuccess = function () {
                    var original = btn.innerHTML;
                    btn.classList.add('copied');
                    btn.innerHTML = '<i class="bi bi-check-lg"></i> Kopyalandı';
                    setTimeout(function () {
                        btn.classList.remove('copied');
                        btn.innerHTML = original;
                    }, 1500);
                };

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(doneSuccess).catch(function () {
                        // Fallback: select + execCommand
                        target.focus();
                        target.select();
                        try { document.execCommand('copy'); doneSuccess(); } catch (e) { /* ignore */ }
                    });
                } else {
                    target.focus();
                    target.select();
                    try { document.execCommand('copy'); doneSuccess(); } catch (e) { /* ignore */ }
                }
            });
        });

        // Bootstrap tooltip init (Client Key/Secret help)
        if (window.bootstrap && window.bootstrap.Tooltip) {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                new bootstrap.Tooltip(el);
            });
        }
    });
</script>
@endpush
