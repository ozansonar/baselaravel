@extends('layouts.admin')

@section('title', 'Proje Ayarları')
@section('page_title', 'Proje Ayarları')
@section('page_description', 'Uygulama yapılandırması, iletişim bilgileri, SEO, entegrasyonlar ve sistem tercihleri')

@php
    $s = fn(string $key, ?string $default = null): ?string => ($settings[$key] ?? null)?->value ?? $default;
@endphp

@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('assets/vendor/glightbox/css/glightbox.min.css') }}">
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
            <a href="#stg-telegram" class="stg-nav-item" onclick="switchSettingsTab(this,'stg-telegram')">
                <i class="bi bi-telegram"></i>
                <div><span>Telegram</span><small>Hata bildirimleri</small></div>
            </a>
            <a href="#stg-regional" class="stg-nav-item" onclick="switchSettingsTab(this,'stg-regional')">
                <i class="bi bi-clock"></i>
                <div><span>Saat Dilimi</span><small>Tarih ve saat gösterimi</small></div>
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
            <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" data-validate novalidate>
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
                               value="{{ $s('site_name', config('app.name')) }}" placeholder="Projenizin adını girin" data-validation-engine="validate[maxSize[10000]]">
                        <small class="stg-hint">Proje genelinde kullanılacak ana isim (navbar, footer, e-postalar, SEO vb.)</small>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Site Başlığı</label>
                        <input type="text" class="stg-input" name="settings[site_title]"
                               value="{{ $s('site_title') }}" placeholder="Site başlığını girin" data-validation-engine="validate[maxSize[10000]]">
                        <small class="stg-hint">Tarayıcı sekmesinde ve başlık çubuğunda görüntülenir</small>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Site Açıklaması</label>
                        <textarea class="stg-textarea" name="settings[site_description]" rows="3"
                                  placeholder="Kısa bir açıklama yazın" data-validation-engine="validate[maxSize[10000]]">{{ $s('site_description') }}</textarea>
                        <small class="stg-hint">Ana sayfada ve meta description'da kullanılır</small>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Site Anahtar Kelimeleri</label>
                        <textarea class="stg-textarea" name="settings[site_keywords]" rows="2"
                                  placeholder="virgülle ayırarak yazın" data-validation-engine="validate[maxSize[10000]]">{{ $s('site_keywords') }}</textarea>
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
                                <input type="file" id="logoFileInput" name="files[site_logo]" accept="image/png,image/jpeg,image/svg+xml,image/webp" hidden data-validation-engine="validate[funcCall[FormValidation.rules.imageFile]]" data-max-size="1" data-accept="image/png,image/jpeg,image/svg+xml,image/webp">
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
                                <input type="file" id="faviconFileInput" name="files[site_favicon]" accept="image/png,image/x-icon,image/svg+xml" hidden data-validation-engine="validate[funcCall[FormValidation.rules.imageFile]]" data-max-size="1" data-accept="image/png,image/x-icon,image/svg+xml">
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
                               value="{{ $s('footer_text') }}" placeholder="Footer metin bilgisi" data-validation-engine="validate[maxSize[10000]]">
                        <small class="stg-hint">Site alt kısmında görünecek telif hakkı metni</small>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Footer Kredi Metni</label>
                        <input type="text" class="stg-input" name="settings[footer_credit]"
                               value="{{ $s('footer_credit') }}" placeholder="Örn: Acme Yazılım tarafından geliştirildi" data-validation-engine="validate[maxSize[10000]]">
                        <small class="stg-hint">Footer'ın sağ tarafında görünür. Boş bırakılırsa hiç gösterilmez.</small>
                    </div>
                </div>
            </form>
        </div>

        {{-- ══════════════ 2. İLETİŞİM & ADRES ══════════════ --}}
        <div class="stg-panel" id="stg-contact">
            <form method="POST" action="{{ route('admin.settings.update') }}" data-validate novalidate>
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
                                   value="{{ $s('contact_phone') }}" placeholder="+90 555 123 45 67" data-validation-engine="validate[maxSize[10000]]">
                        </div>
                        <div class="stg-field stg-half">
                            <label class="stg-label">Telefon (İkincil)</label>
                            <input type="text" class="stg-input" name="settings[contact_phone_2]"
                                   value="{{ $s('contact_phone_2') }}" placeholder="+90 555 987 65 43" data-validation-engine="validate[maxSize[10000]]">
                        </div>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">E-posta Adresi</label>
                        <input type="text" class="stg-input" name="settings[contact_email]"
                               value="{{ $s('contact_email') }}" placeholder="iletisim@domain.com" data-validation-engine="validate[custom[email],maxSize[10000]]">
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Yönetici Bildirim E-postası</label>
                        <input type="text" class="stg-input" name="settings[admin_notification_email]"
                               value="{{ $s('admin_notification_email') }}" placeholder="bildirim@domain.com" data-validation-engine="validate[custom[email],maxSize[10000]]">
                        <small class="stg-help-text">Sistem bildirimleri ve hata uyarıları bu adrese gönderilir. Boşsa "İletişim E-posta Adresi" kullanılır.</small>
                    </div>
                </div>

                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>Adres Bilgileri</h6>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Açık Adres</label>
                        <textarea class="stg-textarea" name="settings[contact_address]" rows="3"
                                  placeholder="Tam adres bilgisi" data-validation-engine="validate[maxSize[10000]]">{{ $s('contact_address') }}</textarea>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Google Maps Embed Kodu</label>
                        <textarea class="stg-textarea" name="settings[contact_map_embed]" rows="4"
                                  placeholder="<iframe src='...'></iframe> veya Google Maps linki" data-validation-engine="validate[maxSize[10000]]">{{ $s('contact_map_embed') }}</textarea>
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
                                   value="{{ $s('working_hours_weekday', '08:00 - 18:00') }}" placeholder="08:00 - 18:00" data-validation-engine="validate[maxSize[10000]]">
                        </div>
                        <div class="stg-field stg-half">
                            <label class="stg-label">Cumartesi</label>
                            <input type="text" class="stg-input" name="settings[working_hours_saturday]"
                                   value="{{ $s('working_hours_saturday', '09:00 - 16:00') }}" placeholder="09:00 - 16:00" data-validation-engine="validate[maxSize[10000]]">
                        </div>
                    </div>
                    <div class="stg-field">
                        <label class="stg-label">Pazar</label>
                        <input type="text" class="stg-input" name="settings[working_hours_sunday]"
                               value="{{ $s('working_hours_sunday', 'Kapalı') }}" placeholder="Kapalı" data-validation-engine="validate[maxSize[10000]]">
                    </div>
                </div>
            </form>
        </div>

        {{-- ══════════════ 3. SOSYAL MEDYA ══════════════ --}}
        <div class="stg-panel" id="stg-social">
            <form method="POST" action="{{ route('admin.settings.update') }}" data-validate novalidate>
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
                        <input type="text" class="stg-input" name="settings[social_facebook]"
                               value="{{ $s('social_facebook') }}" placeholder="https://facebook.com/sayfaniz" data-validation-engine="validate[custom[url],maxSize[10000]]">
                    </div>

                    <div class="stg-field">
                        <label class="stg-label"><i class="bi bi-instagram me-2 text-instagram"></i>Instagram</label>
                        <input type="text" class="stg-input" name="settings[social_instagram]"
                               value="{{ $s('social_instagram') }}" placeholder="https://instagram.com/sayfaniz" data-validation-engine="validate[custom[url],maxSize[10000]]">
                    </div>

                    <div class="stg-field">
                        <label class="stg-label"><i class="bi bi-twitter-x me-2"></i>X (Twitter)</label>
                        <input type="text" class="stg-input" name="settings[social_twitter]"
                               value="{{ $s('social_twitter') }}" placeholder="https://x.com/sayfaniz" data-validation-engine="validate[custom[url],maxSize[10000]]">
                    </div>

                    <div class="stg-field">
                        <label class="stg-label"><i class="bi bi-youtube me-2 text-danger"></i>YouTube</label>
                        <input type="text" class="stg-input" name="settings[social_youtube]"
                               value="{{ $s('social_youtube') }}" placeholder="https://youtube.com/@kanaliniz" data-validation-engine="validate[custom[url],maxSize[10000]]">
                    </div>

                    <div class="stg-field">
                        <label class="stg-label"><i class="bi bi-whatsapp me-2 text-success"></i>WhatsApp</label>
                        <input type="text" class="stg-input" name="settings[social_whatsapp]"
                               value="{{ $s('social_whatsapp') }}" placeholder="https://wa.me/905551234567 veya telefon numarası" data-validation-engine="validate[maxSize[10000]]">
                        <small class="stg-hint">WhatsApp linki veya telefon numarası (ör: +905051234567)</small>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label"><i class="bi bi-tiktok me-2"></i>TikTok</label>
                        <input type="text" class="stg-input" name="settings[social_tiktok]"
                               value="{{ $s('social_tiktok') }}" placeholder="https://tiktok.com/@sayfaniz" data-validation-engine="validate[custom[url],maxSize[10000]]">
                    </div>
                </div>
            </form>
        </div>

        {{-- ══════════════ 4. SEO & META ══════════════ --}}
        <div class="stg-panel" id="stg-seo">
            <form method="POST" action="{{ route('admin.settings.update') }}" data-validate novalidate>
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
                               value="{{ $s('seo_home_title') }}" placeholder="Kurumsal Web Sitesi | {{ $s('site_name', config('app.name')) }}" data-validation-engine="validate[maxSize[10000]]">
                        <small class="stg-hint">60 karakter altında tutmanız önerilir</small>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Ana Sayfa Açıklaması (Meta Description)</label>
                        <textarea class="stg-textarea" name="settings[seo_home_description]" rows="3"
                                  placeholder="Arama sonuçlarında görünecek açıklama" data-validation-engine="validate[maxSize[10000]]">{{ $s('seo_home_description') }}</textarea>
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
                                   value="{{ $s('google_analytics_id') }}" placeholder="G-XXXXXXXXXX" data-validation-engine="validate[maxSize[10000]]">
                        </div>
                        <small class="stg-hint">Google Analytics 4 ölçüm kimliği</small>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Google Tag Manager ID</label>
                        <div class="stg-input-group">
                            <span class="stg-input-prefix">GTM</span>
                            <input type="text" class="stg-input" name="settings[google_tag_manager_id]"
                                   value="{{ $s('google_tag_manager_id') }}" placeholder="GTM-XXXXXXX" data-validation-engine="validate[maxSize[10000]]">
                        </div>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Facebook Pixel ID</label>
                        <input type="text" class="stg-input" name="settings[facebook_pixel_id]"
                               value="{{ $s('facebook_pixel_id') }}" placeholder="XXXXXXXXXXXXXXX" data-validation-engine="validate[maxSize[10000]]">
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Özel Head Kodu</label>
                        <textarea class="stg-textarea font-mono" name="settings[custom_head_code]" rows="4"
                                  placeholder="<script> veya <meta> etiketleri" data-validation-engine="validate[maxSize[10000]]">{{ $s('custom_head_code') }}</textarea>
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
                               value="{{ $s('og_title') }}" placeholder="{{ $s('site_name', config('app.name')) }} - Kurumsal Web Sitesi" data-validation-engine="validate[maxSize[10000]]">
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">OG Açıklama</label>
                        <textarea class="stg-textarea" name="settings[og_description]" rows="2"
                                  placeholder="Sosyal medya paylaşımlarında görünecek açıklama" data-validation-engine="validate[maxSize[10000]]">{{ $s('og_description') }}</textarea>
                    </div>
                </div>
            </form>
        </div>

        {{-- ══════════════ 6. GÖRÜNÜM ══════════════ --}}
        <div class="stg-panel" id="stg-appearance">
            <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" data-validate novalidate>
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
                                <input type="file" id="ogImageInput" name="files[og_image]" accept="image/*" hidden data-validation-engine="validate[funcCall[FormValidation.rules.imageFile]]" data-max-size="1" data-accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml">
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
                                       {{ $s('registration_enabled', '1') === '1' ? 'checked' : '' }} data-fv-ignore>
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
                                       onchange="document.getElementById('maintenanceDetails').classList.toggle('d-none', !this.checked)" data-fv-ignore>
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
                                      placeholder="Sitemiz şu anda planlı bakım çalışması nedeniyle geçici olarak kullanım dışıdır." data-validation-engine="validate[maxSize[10000]]">{{ $s('maintenance_message') }}</textarea>
                            <small class="stg-hint">Boş bırakılırsa varsayılan mesaj gösterilir</small>
                        </div>

                        <div class="stg-field">
                            <label class="stg-label">İzin Verilen IP Adresleri</label>
                            <textarea name="settings[maintenance_allowed_ips]" class="stg-input" rows="4"
                                      placeholder="192.168.1.1&#10;10.0.0.1" data-validation-engine="validate[maxSize[10000]]">{{ $s('maintenance_allowed_ips') }}</textarea>
                            <small class="stg-hint">Her satıra bir IP adresi yazın. Bu IP'lerden gelen ziyaretçiler bakım modunda da siteyi görebilir.</small>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        {{-- ══════════════ 7. E-POSTA (SMTP) ══════════════ --}}
        <div class="stg-panel" id="stg-email">
            <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" data-validate novalidate>
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
                                   value="{{ $s('mail_host') }}" placeholder="smtp.example.com" data-validation-engine="validate[maxSize[10000]]">
                        </div>
                        <div class="stg-field flex-1">
                            <label class="stg-label">Port</label>
                            <input type="text" class="stg-input" name="settings[mail_port]"
                                   value="{{ $s('mail_port', '587') }}" placeholder="587" data-fv-mask="digits" data-validation-engine="validate[custom[integer],min[1],max[65535]]">
                        </div>
                    </div>

                    <div class="stg-row">
                        <div class="stg-field stg-half">
                            <label class="stg-label">Kullanıcı Adı</label>
                            <input type="text" class="stg-input" name="settings[mail_username]"
                                   value="{{ $s('mail_username') }}" placeholder="user@domain.com" data-validation-engine="validate[maxSize[10000]]">
                        </div>
                        <div class="stg-field stg-half">
                            <label class="stg-label">Şifre</label>
                            <input type="password" class="stg-input" name="settings[mail_password]"
                                   value="{{ $s('mail_password') }}" placeholder="SMTP şifresi" data-validation-engine="validate[maxSize[10000]]">
                        </div>
                    </div>

                    <div class="stg-row">
                        <div class="stg-field stg-half">
                            <label class="stg-label">Şifreleme</label>
                            <select class="stg-select" name="settings[mail_encryption]" data-fv-ignore>
                                @foreach(\App\Enums\MailEncryption::cases() as $enc)
                                    <option value="{{ $enc->value }}" @selected($s('mail_encryption', \App\Enums\MailEncryption::Tls->value) === $enc->value)>{{ $enc->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="stg-field stg-half">
                            <label class="stg-label">Gönderen Adı</label>
                            <input type="text" class="stg-input" name="settings[mail_from_name]"
                                   value="{{ $s('mail_from_name') }}" placeholder="Gönderen adı" data-validation-engine="validate[maxSize[10000]]">
                        </div>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Gönderen E-posta</label>
                        <input type="text" class="stg-input" name="settings[mail_from_address]"
                               value="{{ $s('mail_from_address') }}" placeholder="noreply@domain.com" data-validation-engine="validate[custom[email],maxSize[10000]]">
                    </div>

                </div>

                {{-- Gönderim Limitleri --}}
                <div class="stg-section">
                    <div class="stg-section-title">
                        <h6>Gönderim Limitleri</h6>
                        <p>Toplu gönderim hızının tavanı — mail sağlayıcısı bir hesabı bir anda boşalan listeden dolayı kısıtlar</p>
                    </div>

                    <div class="stg-row">
                        <div class="stg-field stg-half">
                            <label class="stg-label">Saatlik Mail Limiti</label>
                            <input type="text" class="stg-input" name="settings[mail_hourly_limit]"
                                   value="{{ $s('mail_hourly_limit', '100') }}" placeholder="100" data-fv-mask="digits" data-validation-engine="validate[custom[integer],min[0],max[100000]]">
                            <small class="stg-hint">
                                Son 60 dakikada gönderilen mail sayılarak uygulanır, hiçbir koşulda aşılmaz.
                                Sağlayıcınızın verdiği saatlik kotayı yazın. 0 yazarsanız gönderim durur.
                            </small>
                        </div>
                        <div class="stg-field stg-half">
                            <label class="stg-label">Tur Başına Mail</label>
                            <input type="text" class="stg-input" name="settings[mail_batch_max]"
                                   value="{{ $s('mail_batch_max', '0') }}" placeholder="0" data-fv-mask="digits" data-validation-engine="validate[custom[integer],min[0],max[100000]]">
                            <small class="stg-hint">
                                Zamanlanmış görev {{ \App\Services\CampaignDispatcher::RUN_INTERVAL_MINUTES }} dakikada bir çalışır.
                                <strong>0</strong> bırakırsanız saatlik limit turlara kendiliğinden bölünür — önerilen budur.
                            </small>
                        </div>
                    </div>

                    <div class="stg-field stg-half">
                        <label class="stg-label">Yeniden Deneme Sayısı</label>
                        <input type="text" class="stg-input" name="settings[mail_max_attempts]"
                               value="{{ $s('mail_max_attempts', '3') }}" placeholder="3" data-fv-mask="digits" data-validation-engine="validate[custom[integer],min[1],max[10]]">
                        <small class="stg-hint">Gönderilemeyen bir mail kaç kez daha denensin. Bu sayıya ulaşan alıcı başarısız sayılır.</small>
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
                        <input type="text" class="stg-input" placeholder="test@example.com" id="testEmailInput" data-fv-ignore>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Konu</label>
                        <input type="text" class="stg-input" id="testEmailSubject"
                               value="{{ $s('site_name', config('app.name')) }} — E-posta Bilgilendirmesi" data-fv-ignore>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Mesaj</label>
                        <textarea class="stg-textarea" id="testEmailMessage" rows="5" data-fv-ignore>Merhaba, bu e-posta {{ $s('site_name', config('app.name')) }} platformu üzerinden gönderilmiştir. E-posta yapılandırmanız başarıyla tamamlanmıştır. Herhangi bir sorunuz olursa bizimle iletişime geçebilirsiniz.</textarea>
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
                                <input type="file" id="mailLogoFileInput" name="files[mail_logo]" accept="image/png,image/jpeg,image/webp" hidden data-validation-engine="validate[funcCall[FormValidation.rules.imageFile]]" data-max-size="1" data-accept="image/png,image/jpeg,image/webp">
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
                                       {{ $s('mail_mode', 'normal') === 'normal' ? 'checked' : '' }} data-fv-ignore>
                                <i class="bi bi-send-check"></i>
                                <strong>Normal Mod</strong>
                                <small>Mailler asıl alıcıya gider</small>
                            </label>
                            <label class="stg-mode-card {{ $s('mail_mode') === 'developer' ? 'active' : '' }}">
                                <input type="radio" name="settings[mail_mode]" value="developer"
                                       {{ $s('mail_mode') === 'developer' ? 'checked' : '' }} data-fv-ignore>
                                <i class="bi bi-bug"></i>
                                <strong>Developer / Test Mod</strong>
                                <small>Tüm mailler test adreslerine yönlendirilir</small>
                            </label>
                        </div>
                    </div>

                    <div class="stg-field stg-mail-test-addresses {{ $s('mail_mode') === 'developer' ? '' : 'd-none' }}" id="mailTestAddressesField">
                        <label class="stg-label">Test E-posta Adresleri</label>
                        <input type="text" class="stg-input" name="settings[mail_test_addresses]"
                               value="{{ $s('mail_test_addresses') }}" placeholder="ornek@gmail.com,diger@gmail.com" data-validation-engine="validate[maxSize[10000]]">
                        <small class="stg-hint">Virgülle ayırarak birden fazla adres yazabilirsiniz. Tüm giden mailler bu adreslere yönlendirilecektir.</small>
                    </div>
                </div>
            </form>
        </div>

        {{-- ══════════════ 8. MAİL TEMASI ══════════════ --}}
        <div class="stg-panel" id="stg-mail-theme">
            <form method="POST" action="{{ route('admin.settings.update') }}" data-validate novalidate>
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
                                       value="{{ $s('mail_theme_primary_color', '#4f46e5') }}" data-fv-ignore>
                                <input type="text" class="stg-input stg-color-hex" id="mtPrimaryColorHex"
                                       value="{{ $s('mail_theme_primary_color', '#4f46e5') }}" maxlength="7" data-fv-ignore readonly>
                            </div>
                        </div>
                        <div class="stg-field stg-half">
                            <label class="stg-label">Koyu Ana Renk (Primary Dark)</label>
                            <div class="stg-color-field">
                                <input type="color" class="stg-color-picker" id="mtPrimaryDarkColor"
                                       name="settings[mail_theme_primary_dark_color]"
                                       value="{{ $s('mail_theme_primary_dark_color', '#4338ca') }}" data-fv-ignore>
                                <input type="text" class="stg-input stg-color-hex" id="mtPrimaryDarkColorHex"
                                       value="{{ $s('mail_theme_primary_dark_color', '#4338ca') }}" maxlength="7" data-fv-ignore readonly>
                            </div>
                        </div>
                    </div>

                    <div class="stg-row">
                        <div class="stg-field stg-half">
                            <label class="stg-label">Arka Plan Rengi</label>
                            <div class="stg-color-field">
                                <input type="color" class="stg-color-picker" id="mtBgColor"
                                       name="settings[mail_theme_bg_color]"
                                       value="{{ $s('mail_theme_bg_color', '#f8fafc') }}" data-fv-ignore>
                                <input type="text" class="stg-input stg-color-hex" id="mtBgColorHex"
                                       value="{{ $s('mail_theme_bg_color', '#f8fafc') }}" maxlength="7" data-fv-ignore readonly>
                            </div>
                        </div>
                        <div class="stg-field stg-half">
                            <label class="stg-label">Kart Arka Planı</label>
                            <div class="stg-color-field">
                                <input type="color" class="stg-color-picker" id="mtCardBgColor"
                                       name="settings[mail_theme_card_bg_color]"
                                       value="{{ $s('mail_theme_card_bg_color', '#ffffff') }}" data-fv-ignore>
                                <input type="text" class="stg-input stg-color-hex" id="mtCardBgColorHex"
                                       value="{{ $s('mail_theme_card_bg_color', '#ffffff') }}" maxlength="7" data-fv-ignore readonly>
                            </div>
                        </div>
                    </div>

                    <div class="stg-row">
                        <div class="stg-field stg-half">
                            <label class="stg-label">Metin Rengi</label>
                            <div class="stg-color-field">
                                <input type="color" class="stg-color-picker" id="mtTextColor"
                                       name="settings[mail_theme_text_color]"
                                       value="{{ $s('mail_theme_text_color', '#334155') }}" data-fv-ignore>
                                <input type="text" class="stg-input stg-color-hex" id="mtTextColorHex"
                                       value="{{ $s('mail_theme_text_color', '#334155') }}" maxlength="7" data-fv-ignore readonly>
                            </div>
                        </div>
                        <div class="stg-field stg-half">
                            <label class="stg-label">Soluk Metin Rengi</label>
                            <div class="stg-color-field">
                                <input type="color" class="stg-color-picker" id="mtMutedColor"
                                       name="settings[mail_theme_muted_color]"
                                       value="{{ $s('mail_theme_muted_color', '#64748b') }}" data-fv-ignore>
                                <input type="text" class="stg-input stg-color-hex" id="mtMutedColorHex"
                                       value="{{ $s('mail_theme_muted_color', '#64748b') }}" maxlength="7" data-fv-ignore readonly>
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
                                  placeholder="Sizinle çalışmaktan mutluluk duyuyoruz." data-validation-engine="validate[maxSize[10000]]">{{ $s('mail_theme_footer_text', 'Sizinle çalışmaktan mutluluk duyuyoruz.') }}</textarea>
                        <small class="stg-hint">E-posta footer bölümünde görünecek açıklama metni</small>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Sosyal Medya Linkleri</label>
                        <div class="stg-switch-row">
                            <label class="stg-switch">
                                <input type="hidden" name="settings[mail_theme_social_links]" value="0">
                                <input type="checkbox" name="settings[mail_theme_social_links]" value="1"
                                       id="mtSocialLinks"
                                       {{ $s('mail_theme_social_links', '1') === '1' ? 'checked' : '' }} data-fv-ignore>
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
                                    <p class="stg-mp-footer-text" id="mpFooterText">{{ $s('mail_theme_footer_text', 'Sizinle çalışmaktan mutluluk duyuyoruz.') }}</p>
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
            <form method="POST" action="{{ route('admin.settings.update') }}" data-validate novalidate>
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
                                       {{ $s('recaptcha_enabled', '0') === '1' ? 'checked' : '' }} data-fv-ignore>
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
                               value="{{ $s('recaptcha_site_key') }}" placeholder="6Lc..." data-validation-engine="validate[maxSize[10000]]">
                        <small class="stg-hint">Google reCAPTCHA admin panelinden aldığınız site anahtarı</small>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Secret Key (Private Key)</label>
                        <input type="password" class="stg-input" name="settings[recaptcha_secret_key]"
                               value="" placeholder="{{ $s('recaptcha_secret_key') ? '●●●●●●●● (değiştirmek için yeni key girin)' : '' }}" data-validation-engine="validate[maxSize[10000]]">
                        <small class="stg-hint">Google reCAPTCHA admin panelinden aldığınız gizli anahtar</small>
                    </div>
                </div>

            </form>
        </div>


        {{-- ══════════════ TELEGRAM BİLDİRİMLERİ ══════════════ --}}
        <div class="stg-panel" id="stg-telegram">
            <form method="POST" action="{{ route('admin.settings.update') }}" data-validate novalidate>
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
                                       value="1" {{ $tgEnabled ? 'checked' : '' }} data-fv-ignore>
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
                               autocomplete="new-password" data-validation-engine="validate[maxSize[10000]]">
                        <small class="stg-hint">
                            <a href="https://t.me/BotFather" target="_blank" rel="noopener" class="text-teal">@BotFather</a> üzerinden <code>/newbot</code> komutu ile yeni bir bot oluşturup token alabilirsin. Boş bırakırsan mevcut değer korunur.
                        </small>
                    </div>

                    <div class="stg-field">
                        <label class="stg-label">Chat ID</label>
                        <input type="text" class="stg-input" name="settings[telegram_chat_id]"
                               value="{{ $s('telegram_chat_id') }}" placeholder="123456789 veya -1001234567890" data-validation-engine="validate[maxSize[10000]]">
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
                        @php $tgLevel = $s('telegram_notify_level', \App\Enums\TelegramNotifyLevel::default()->value); @endphp
                        <select class="stg-input" name="settings[telegram_notify_level]" data-fv-ignore>
                            @foreach(\App\Enums\TelegramNotifyLevel::cases() as $level)
                                <option value="{{ $level->value }}" @selected($tgLevel === $level->value)>{{ $level->label() }}</option>
                            @endforeach
                        </select>
                        <small class="stg-hint">
                            @foreach(\App\Enums\TelegramNotifyLevel::cases() as $level)
                                <strong>{{ $level->label() }}:</strong> {{ $level->description() }}@if(!$loop->last)<br>@endif
                            @endforeach
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

        {{-- ══════════════ 12. BÖLGESEL AYARLAR ══════════════ --}}
        <div class="stg-panel" id="stg-regional">
            <form method="POST" action="{{ route('admin.settings.update') }}" data-validate novalidate>
                @csrf
                @method('PUT')

                <div class="stg-section">
                    <div class="stg-panel-header">
                        <h5><i class="bi bi-clock me-2 text-teal"></i>Saat Dilimi</h5>
                        <p>Tarih ve saat gösteriminde kullanılacak saat dilimi</p>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="stg-field">
                                <label class="stg-label">Saat Dilimi</label>
                                <select class="stg-select" name="settings[app_timezone]" data-fv-ignore>
                                    @php $currentTz = $s('app_timezone', \App\Enums\AppTimezone::default()->value); @endphp
                                    @foreach(\App\Enums\AppTimezone::cases() as $tz)
                                        <option value="{{ $tz->value }}" @selected($currentTz === $tz->value)>{{ $tz->label() }}</option>
                                    @endforeach
                                </select>
                                <span class="stg-hint">
                                    Blog yayın tarihleri, mail logları, canlı ziyaretçi ekranı ve kampanya
                                    zamanlaması dahil, tarih gösterilen her yerde kullanılır.
                                    Şu an: <strong>{{ now()->format('d.m.Y H:i') }}</strong>
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- Language used to live here as a second dropdown that nothing read.
                         The real default language is the starred row on the Diller screen. --}}
                    <div class="alert alert-info mt-3 mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        <strong>Site dili</strong> buradan değil
                        @can('viewAny', \App\Models\Language::class)
                            <a href="{{ route('admin.languages.index') }}" class="alert-link">Diller</a>
                        @else
                            <strong>Diller</strong>
                        @endcan
                        ekranından yönetilir; oradaki varsayılan dil, tarayıcı diline uymayan
                        ziyaretçilere gösterilen dildir. Arayüz metinleri için
                        @can('viewAny', \App\Models\Translation::class)
                            <a href="{{ route('admin.translations.index') }}" class="alert-link">Dil Yazıları</a>
                        @else
                            <strong>Dil Yazıları</strong>
                        @endcan
                        ekranına bakın.
                    </div>
                </div>

                <div class="stg-form-actions">
                    <button type="submit" class="btn-teal">
                        <i class="bi bi-check-lg me-1"></i> Kaydet
                    </button>
                </div>
            </form>
        </div>

        <div class="stg-panel" id="stg-system">

            @php
                $durumSinifi = ['ok' => 'sys-ok', 'warn' => 'sys-warn', 'danger' => 'sys-danger'];
                $durumIkonu  = ['ok' => 'bi-check-circle-fill', 'warn' => 'bi-exclamation-triangle-fill', 'danger' => 'bi-x-octagon-fill'];
                $disk = $systemInfo['disk'];
                $canliDebug = $systemInfo['debug'] && $systemInfo['environment'] === 'production';
            @endphp

            {{-- Tek cümlelik özet: ekranı açan kişi önce "sorun var mı" sorusunun
                 cevabını görsün, ayrıntıya sonra insin. --}}
            <div class="sys-verdict {{ $durumSinifi[$verdict['state']] }}">
                <i class="bi {{ $durumIkonu[$verdict['state']] }}"></i>
                <div>
                    <strong>{{ $verdict['title'] }}</strong>
                    <span>{{ $verdict['detail'] }}</span>
                </div>
            </div>

            {{-- Sistem Durumu --}}
            <div class="stg-section">
                <div class="stg-section-title">
                    <h6><i class="bi bi-hdd-stack"></i> Sistem Durumu</h6>
                    <p>Uygulamanın üzerinde çalıştığı ortam</p>
                </div>

                <div class="sys-grid">
                    <div class="sys-card">
                        <span class="sys-card__label"><i class="bi bi-hdd-network"></i> Web sunucusu</span>
                        <strong class="sys-card__value">{{ \Illuminate\Support\Str::limit($systemInfo['server_software'], 28) }}</strong>
                        <span class="sys-card__meta">PHP arayüzü: {{ $systemInfo['php_sapi'] }}</span>
                    </div>

                    <div class="sys-card {{ $systemInfo['db']['connected'] ? '' : 'sys-card--danger' }}">
                        <span class="sys-card__label"><i class="bi bi-database"></i> Veritabanı</span>
                        <strong class="sys-card__value">
                            @if($systemInfo['db']['connected'])
                                {{ strtoupper($systemInfo['db']['driver']) }} {{ \Illuminate\Support\Str::before($systemInfo['db']['version'] ?? '', '-') }}
                            @else
                                Bağlantı yok
                            @endif
                        </strong>
                        {{-- SQLite'ta ad yerine tam dosya yolu geliyor; kartı taşırmasın. --}}
                        <span class="sys-card__meta" title="{{ $systemInfo['db']['name'] }}">
                            {{ $systemInfo['db']['connected']
                                ? \Illuminate\Support\Str::limit(basename((string) $systemInfo['db']['name']), 32)
                                : 'Ayarları kontrol edin' }}
                        </span>
                    </div>

                    <div class="sys-card">
                        <span class="sys-card__label"><i class="bi bi-filetype-php"></i> PHP</span>
                        <strong class="sys-card__value">{{ $systemInfo['php_version'] }}</strong>
                        <span class="sys-card__meta">Laravel {{ $systemInfo['laravel_version'] }}</span>
                    </div>

                    <div class="sys-card {{ $canliDebug ? 'sys-card--danger' : '' }}">
                        <span class="sys-card__label"><i class="bi bi-toggles"></i> Ortam</span>
                        <strong class="sys-card__value">{{ $systemInfo['environment'] }}</strong>
                        <span class="sys-card__meta">
                            @if($canliDebug)
                                Hata ayıklama açık — canlıda kapatın
                            @else
                                Hata ayıklama {{ $systemInfo['debug'] ? 'açık' : 'kapalı' }} · {{ $systemInfo['timezone'] }}
                            @endif
                        </span>
                    </div>

                    <div class="sys-card">
                        <span class="sys-card__label"><i class="bi bi-lightning-charge"></i> Önbellek / kuyruk</span>
                        <strong class="sys-card__value">{{ $systemInfo['cache_driver'] }} · {{ $systemInfo['queue_driver'] }}</strong>
                        <span class="sys-card__meta">
                            storage klasörü {{ $systemInfo['storage_writable'] ? 'yazılabilir' : 'yazılamıyor' }}
                        </span>
                    </div>

                    @if($disk['total'] > 0)
                        <div class="sys-card">
                            <span class="sys-card__label"><i class="bi bi-hdd"></i> Disk</span>
                            <strong class="sys-card__value">{{ $disk['free_human'] }} boş</strong>
                            <div class="sys-meter" role="img"
                                 aria-label="Diskin yüzde {{ $disk['used_percent'] }} kadarı dolu">
                                <span class="sys-meter__fill {{ $disk['used_percent'] >= 90 ? 'is-full' : '' }}"
                                      style="--sys-meter: {{ $disk['used_percent'] }}%"></span>
                            </div>
                            <span class="sys-card__meta">
                                {{ $disk['total_human'] }} alanın %{{ $disk['used_percent'] }} kadarı dolu
                            </span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Yükleme limitleri --}}
            <div class="stg-section">
                <div class="stg-section-title">
                    <h6><i class="bi bi-cloud-upload"></i> Dosya Yükleme Limitleri</h6>
                    <p>Bu değerler web isteklerinde geçerli olan PHP ayarları ({{ $systemInfo['php_sapi'] }})</p>
                </div>

                <div class="alert alert-info sys-note">
                    <i class="bi bi-info-circle me-1"></i>
                    Bu tavanlar aşıldığında yükleme <strong>hata vermeden</strong> düşer: tarayıcı dosyayı gönderir,
                    PHP gövdeyi kabul etmez ve form boş gelir. Panelden büyük görsel ya da video yükleyecekseniz
                    iki sınırın da <strong>{{ $systemInfo['recommended_upload_human'] }}</strong>
                    ve üzerinde olması gerekir. Komut satırındaki değerler (<code>php -i</code>) web ile aynı olmayabilir.
                </div>

                <div class="sys-limits">
                    @foreach($systemInfo['limits'] as $limit)
                        @php
                            $oran = $limit['recommended'] > 0 && $limit['bytes'] > 0
                                ? min(100, (int) round(($limit['bytes'] / $limit['recommended']) * 100))
                                : 100;
                        @endphp
                        <div class="sys-limit {{ $durumSinifi[$limit['state']] }}">
                            <div class="sys-limit__head">
                                <span class="sys-limit__label">
                                    <i class="bi {{ $durumIkonu[$limit['state']] }}"></i>
                                    {{ $limit['label'] }}
                                    <code>{{ $limit['key'] }}</code>
                                </span>
                                <span class="sys-limit__value">
                                    {{ $limit['value'] }}
                                    @if($limit['state'] !== 'ok' && $limit['key'] !== 'max_execution_time')
                                        <small>/ önerilen {{ $limit['recommended_human'] }}</small>
                                    @endif
                                </span>
                            </div>
                            <div class="sys-meter">
                                <span class="sys-meter__fill" style="--sys-meter: {{ $oran }}%"></span>
                            </div>
                            <p class="sys-limit__note">{{ $limit['note'] }}</p>
                        </div>
                    @endforeach
                </div>

                @php
                    $dusukler = array_filter($systemInfo['limits'], fn (array $row): bool => $row['state'] === 'danger');
                @endphp

                @if($dusukler !== [])
                    <div class="sys-fix">
                        <div class="sys-fix__head">
                            <strong><i class="bi bi-wrench-adjustable me-1"></i>Nasıl düzeltilir</strong>
                            <button type="button" class="btn-glass btn-sm stg-copy-btn" data-copy-target="phpIniSnippet">
                                <i class="bi bi-clipboard"></i> Ayarları kopyala
                            </button>
                        </div>
                        <p class="sys-fix__text">
                            Hosting panelinizde <strong>PHP ayarları</strong> (ya da sunucuda
                            <code>php.ini</code> / PHP-FPM havuz dosyası) bölümüne aşağıdaki satırları yazın,
                            ardından PHP servisini yeniden başlatın. Paylaşımlı hostinglerde bu alan genelde
                            "PHP Selector" ya da "MultiPHP INI Editor" adıyla geçer.
                        </p>
<pre class="sys-fix__code" id="phpIniSnippet">upload_max_filesize = 128M
post_max_size = 128M
memory_limit = 256M
max_execution_time = 120</pre>
                    </div>
                @endif
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
        var primary = (document.getElementById('mtPrimaryColor') || {}).value || '#4f46e5';
        var primaryDark = (document.getElementById('mtPrimaryDarkColor') || {}).value || '#4338ca';
        var bg = (document.getElementById('mtBgColor') || {}).value || '#f8fafc';
        var cardBg = (document.getElementById('mtCardBgColor') || {}).value || '#ffffff';
        var text = (document.getElementById('mtTextColor') || {}).value || '#334155';
        var muted = (document.getElementById('mtMutedColor') || {}).value || '#64748b';

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
            if (el) el.textContent = this.value || 'Sizinle çalışmaktan mutluluk duyuyoruz.';
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
{{-- Kütüphane projede duruyor; CDN'e erişilemediğinde görsel önizleme
     sessizce çalışmıyordu. --}}
<script src="{{ versioned_asset('assets/vendor/glightbox/js/glightbox.min.js') }}"></script>
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
