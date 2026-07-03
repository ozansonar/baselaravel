{{--
    TikTok Audit (Onay) Başvurusu — Adım Adım Rehber
    Settings → TikTok panel'inin başına include edilir.

    Tüm metinler kopyala butonu ile birlikte. Kullanıcı bu sayfayı
    açıp adım adım takip edebilir, dış doküman aramaya gerek yok.

    docs/tiktok.md Bölüm 8 referansı.
--}}

<div class="card mb-4 stg-tt-guide" id="tiktokAuditGuide">
    <div class="card-header d-flex justify-content-between align-items-center"
         data-bs-toggle="collapse" data-bs-target="#ttAuditGuideBody"
         aria-expanded="false" aria-controls="ttAuditGuideBody"
         role="button">
        <h5 class="mb-0">
            <i class="bi bi-info-circle-fill me-2 text-warning"></i>
            TikTok Audit (Onay) Başvurusu — Adım Adım Rehber
        </h5>
        <i class="bi bi-chevron-down stg-tt-guide-chevron"></i>
    </div>

    <div class="collapse" id="ttAuditGuideBody">
        <div class="card-body">

            <div class="alert alert-info small mb-4">
                <i class="bi bi-lightbulb me-1"></i>
                <strong>Neden gerekli?</strong> TikTok, üçüncü taraf uygulamaların kendi platformuna
                yayın yapmasından önce <strong>incelemek</strong> ister (Audit). Onay almadan da
                <strong>Inbox modu</strong> ile yarı-otomatik çalışırsın (video TikTok app'ine düşer,
                sen mobilde "Paylaş" tıklarsın). Audit onaylanınca <strong>tam otomatik</strong>
                Direct Post moduna geçersin.
                <br>
                <strong>Süre:</strong> Senin işin 1-2 saat (belge + demo video) + TikTok tarafı bekleme 2-6 hafta.
                <br>
                <strong>Maliyet:</strong> Ücretsiz. Reject olsa bile tekrar başvurabilirsin.
            </div>

            {{-- ═══════════════ ADIM 1 ═══════════════ --}}
            <div class="stg-tt-step">
                <div class="stg-tt-step-num">1</div>
                <div class="stg-tt-step-body">
                    <h6>TikTok Developer Hesabı Aç + Uygulama Oluştur</h6>
                    <ol class="small mb-2">
                        <li>
                            <a href="https://developers.tiktok.com" target="_blank" rel="noopener">developers.tiktok.com</a>
                            adresine git → Sağ üst <strong>Login</strong>
                        </li>
                        <li>İşletme TikTok hesabınla giriş yap (kişisel değil)</li>
                        <li>Sağ üst profil → <strong>Manage apps</strong> → <strong>Connect an app</strong></li>
                        <li>
                            Bilgiler:
                            <ul>
                                <li><strong>App name:</strong> <code>Orhan Babanın Çiftliği — Auto Cross-Post</code></li>
                                <li><strong>Category:</strong> Business</li>
                                <li><strong>App icon:</strong> İşletme logosu (kare, 1024×1024)</li>
                                <li><strong>App description:</strong>
                                    <button type="button" class="btn btn-sm btn-outline-secondary stg-copy-btn"
                                            data-copy-target="ttDescription">
                                        <i class="bi bi-clipboard"></i> Kopyala
                                    </button>
                                </li>
                            </ul>
                        </li>
                    </ol>
                    <textarea readonly class="form-control stg-tt-copy-text" id="ttDescription" rows="2"
                              onclick="this.select()">Orhan Babanın Çiftliği is a family farm in Çorum, Turkey selling natural dairy products. This app automatically cross-posts our blog cover images and Reels videos from our website to our own TikTok business account. Owned-account publishing only — no user-generated content.</textarea>
                </div>
            </div>

            {{-- ═══════════════ ADIM 2 ═══════════════ --}}
            <div class="stg-tt-step">
                <div class="stg-tt-step-num">2</div>
                <div class="stg-tt-step-body">
                    <h6>OAuth Login Kit + Scope (İzinler) Konfigüre Et</h6>
                    <p class="small mb-2">
                        App detayında <strong>Login Kit</strong> sekmesine git.
                    </p>
                    <strong class="small">a) Redirect URI ekle:</strong>
                    <div class="d-flex gap-2 mb-2">
                        <input type="text" class="form-control form-control-sm" id="ttRedirectUri"
                               value="{{ route('admin.tiktok.oauth.callback') }}" readonly onclick="this.select()">
                        <button type="button" class="btn btn-sm btn-outline-secondary stg-copy-btn"
                                data-copy-target="ttRedirectUri">
                            <i class="bi bi-clipboard"></i> Kopyala
                        </button>
                    </div>

                    <strong class="small">b) Scope'lar (izinler) — hepsini işaretle:</strong>
                    <ul class="small mb-2">
                        <li><code>user.info.basic</code> — Kullanıcı bilgisi</li>
                        <li><code>video.publish</code> — Video yayını (kritik)</li>
                        <li><code>video.upload</code> — Video yükleme</li>
                        <li><code>photo.publish</code> — Photo Mode yayını (slideshow için kritik)</li>
                    </ul>

                    <strong class="small">c) Content Posting API capability ekle:</strong>
                    <p class="small mb-0">
                        App detayı → <strong>Add capabilities</strong> → <strong>Content Posting API</strong> → Add.
                        Sandbox için otomatik onay. Production için audit gerekli.
                    </p>
                </div>
            </div>

            {{-- ═══════════════ ADIM 3 ═══════════════ --}}
            <div class="stg-tt-step">
                <div class="stg-tt-step-num">3</div>
                <div class="stg-tt-step-body">
                    <h6>Client Key + Client Secret Al, Aşağıdaki Alanlara Yapıştır</h6>
                    <ol class="small mb-2">
                        <li>App detay sayfasının üst kısmında <strong>Client Key</strong> ve <strong>Client Secret</strong> görünür</li>
                        <li>Her ikisini de kopyala</li>
                        <li>Bu sayfada aşağıdaki <strong>"Uygulama Kimlik Bilgileri"</strong> bölümüne yapıştır</li>
                        <li><strong>Kaydet</strong> butonuna bas</li>
                        <li>Sonra <strong>"TikTok'u Bağla"</strong> butonu ile OAuth akışı başlat (token'lar otomatik kaydedilir)</li>
                    </ol>
                    <div class="alert alert-warning small mb-0">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        <strong>Client Secret tek seferlik gösterilir.</strong>
                        Kaybedersen "Regenerate" tıkla, eski iptal olur.
                    </div>
                </div>
            </div>

            {{-- ═══════════════ ADIM 4 ═══════════════ --}}
            <div class="stg-tt-step">
                <div class="stg-tt-step-num">4</div>
                <div class="stg-tt-step-body">
                    <h6>Domain Doğrulama (TXT DNS record)</h6>
                    <p class="small mb-2">
                        TikTok app detay → <strong>Settings → Verify Domain</strong>. TikTok sana
                        şuna benzer bir kod verir: <code>tiktok-developers-site-verification=xxx</code>
                    </p>
                    <strong class="small">DNS sağlayıcına (Cloudflare / hosting paneli) ekle:</strong>
                    <table class="table table-sm small mb-2">
                        <thead><tr><th>Tip</th><th>İsim</th><th>Değer</th><th>TTL</th></tr></thead>
                        <tbody><tr>
                            <td>TXT</td>
                            <td><code>@</code> veya boş</td>
                            <td><code>tiktok-developers-site-verification=...</code> (TikTok'tan gelen)</td>
                            <td>3600</td>
                        </tr></tbody>
                    </table>
                    <p class="small mb-0">
                        DNS propagasyon 5-30 dakika. Sonra TikTok dashboard'unda <strong>"Verify"</strong> tıkla.
                    </p>
                </div>
            </div>

            {{-- ═══════════════ ADIM 5 ═══════════════ --}}
            <div class="stg-tt-step">
                <div class="stg-tt-step-num">5</div>
                <div class="stg-tt-step-body">
                    <h6>Privacy Policy (Gizlilik) Sayfana TikTok Ek Metnini Ekle</h6>
                    <p class="small mb-2">
                        Sitende zaten <a href="{{ url('/gizlilik') }}" target="_blank">gizlilik politikası sayfası</a>
                        var. Aşağıdaki paragrafı sayfanın <strong>"Üçüncü taraf hizmetler"</strong> veya
                        <strong>"Veri paylaşımı"</strong> bölümüne ekle.
                    </p>
                    <button type="button" class="btn btn-sm btn-outline-secondary stg-copy-btn mb-2"
                            data-copy-target="ttPrivacyText">
                        <i class="bi bi-clipboard"></i> Metni Kopyala
                    </button>
                    <textarea readonly class="form-control stg-tt-copy-text" id="ttPrivacyText" rows="8"
                              onclick="this.select()">TikTok Entegrasyonu

Web sitemiz, blog yazılarımızı ve ürün tanıtımlarımızı otomatik olarak işletmemize ait resmi TikTok hesabına paylaşabilmek için TikTok Content Posting API'sini kullanmaktadır.

Bu süreçte:
- Sadece işletmemizin sahip olduğu TikTok hesabına içerik yüklenir.
- Site ziyaretçilerinin kişisel verileri (e-posta, ad, sipariş bilgisi vb.) TikTok ile PAYLAŞILMAZ.
- TikTok'a yalnızca kendi ürettiğimiz blog görselleri, video içerikleri, başlık ve etiketler iletilir.
- TikTok'un kendi gizlilik politikası için: https://www.tiktok.com/legal/privacy-policy

TikTok bağlantısını istediğiniz zaman Admin Panel → Ayarlar → TikTok bölümünden kaldırabilirsiniz.</textarea>
                </div>
            </div>

            {{-- ═══════════════ ADIM 6 ═══════════════ --}}
            <div class="stg-tt-step">
                <div class="stg-tt-step-num">6</div>
                <div class="stg-tt-step-body">
                    <h6>Terms of Service (Kullanım Koşulları) Sayfana Ek Metin</h6>
                    <p class="small mb-2">
                        <a href="{{ url('/kullanim-sartlari') }}" target="_blank">Kullanım koşulları sayfana</a>
                        ekle:
                    </p>
                    <button type="button" class="btn btn-sm btn-outline-secondary stg-copy-btn mb-2"
                            data-copy-target="ttTosText">
                        <i class="bi bi-clipboard"></i> Metni Kopyala
                    </button>
                    <textarea readonly class="form-control stg-tt-copy-text" id="ttTosText" rows="4"
                              onclick="this.select()">Sosyal Medya Otomasyonu

Sitemiz Instagram, Facebook ve TikTok platformlarına otomatik içerik paylaşımı yapabilir. Bu paylaşımlar yalnızca işletmemize ait resmi sosyal medya hesaplarına yapılır. Kullanıcı verileri sosyal medya platformlarına aktarılmaz. Sosyal medya platformlarının kullanım koşulları kendileri için bağlayıcıdır.</textarea>
                </div>
            </div>

            {{-- ═══════════════ ADIM 7 ═══════════════ --}}
            <div class="stg-tt-step">
                <div class="stg-tt-step-num">7</div>
                <div class="stg-tt-step-body">
                    <h6>Use Case Açıklaması (TikTok başvuru formuna)</h6>
                    <p class="small mb-2">
                        TikTok Developer Console → Submit for review formunda <strong>"How will you use this API?"</strong>
                        sorusuna bu metni yapıştır:
                    </p>
                    <button type="button" class="btn btn-sm btn-outline-secondary stg-copy-btn mb-2"
                            data-copy-target="ttUseCaseText">
                        <i class="bi bi-clipboard"></i> Use Case Metnini Kopyala
                    </button>
                    <textarea readonly class="form-control stg-tt-copy-text" id="ttUseCaseText" rows="14"
                              onclick="this.select()">USE CASE — Owned-Account Cross-Posting for Farm Business

I run "Orhan Babanın Çiftliği" (Orhan Father's Farm), a small family-owned dairy farm located in Büyük Palabıyık Village, Çorum, Turkey. We produce traditional dairy products (cheese, yoghurt, butter, eggs) using ancestral methods and ship them across Turkey.

Our website (https://orhanbabaninciftligi.com) auto-generates SEO-focused blog posts about our products using AI, four times per day via scheduled cron jobs. These blog posts are then cross-posted to our existing social media presence (Instagram and Facebook) automatically.

We want to extend this same automation to TikTok using the Content Posting API for two specific use cases:

1) Photo Mode (slideshow): When a new blog post is published, the AI-generated caption and cover image are cross-posted to TikTok Photo Mode as a single-image slideshow. This helps us reach the TikTok audience without manual effort.

2) Video Reels: When we upload short product/farm video content via our admin panel (with the "Reels" media type selected), it is automatically cross-posted to TikTok as a regular video.

Important boundaries:
- We post ONLY to our OWN TikTok account (no user-generated content, no third-party accounts).
- We do NOT collect TikTok user data of any kind.
- Daily volume is low: 4 automatic cron posts + occasional manual posts = max ~10 posts per day.
- Target audience: Turkish-speaking consumers interested in natural farm products.
- Content is family-friendly, non-political, non-promotional-of-third-parties.
- We use PULL_FROM_URL upload method (TikTok fetches media from our public HTTPS URLs).

The Content Posting API will save us significant manual effort and help us maintain a consistent presence across all major social platforms. Thank you for reviewing our application.</textarea>
                </div>
            </div>

            {{-- ═══════════════ ADIM 8 ═══════════════ --}}
            <div class="stg-tt-step">
                <div class="stg-tt-step-num">8</div>
                <div class="stg-tt-step-body">
                    <h6>Demo Video Çek (30-60 saniye)</h6>
                    <p class="small mb-2">
                        TikTok ekibinin uygulamanı çalışırken görmesi için. Telefon veya bilgisayar ekran kaydı yeterli.
                    </p>
                    <strong class="small">Sahne planı:</strong>
                    <ol class="small mb-2">
                        <li><strong>(0-5 sn)</strong> Web sitesinin homepage'i göster — "orhanbabaninciftligi.com"</li>
                        <li><strong>(5-15 sn)</strong> Admin paneline gir — <code>/admin/instagram-posts/create</code></li>
                        <li><strong>(15-30 sn)</strong> Form doldurma — caption + görsel + <strong>"TikTok'a paylaş"</strong> checkbox işaretle</li>
                        <li><strong>(30-40 sn)</strong> "Yayınla" tıkla → başarı mesajı</li>
                        <li><strong>(40-55 sn)</strong> TikTok app'ini aç → yeni paylaşılan post'u göster</li>
                        <li><strong>(55-60 sn)</strong> Bitiş — "Thank you for reviewing"</li>
                    </ol>
                    <strong class="small">Voice-over (İngilizce alt yazı veya konuşma) — kopyala:</strong>
                    <button type="button" class="btn btn-sm btn-outline-secondary stg-copy-btn mb-2 ms-2"
                            data-copy-target="ttVoiceoverText">
                        <i class="bi bi-clipboard"></i> Kopyala
                    </button>
                    <textarea readonly class="form-control stg-tt-copy-text" id="ttVoiceoverText" rows="6"
                              onclick="this.select()">This is the admin panel of Orhan Babanın Çiftliği, our family farm in Turkey.
We create a blog post about our products.
The system automatically generates a caption and hashtags.
We check the "Share to TikTok" option.
After publishing, the content is automatically cross-posted to our own TikTok account using the Content Posting API.
This saves us hours of manual social media work every week.</textarea>
                    <p class="small text-muted mt-2 mb-0">
                        Çektikten sonra YouTube'a <strong>unlisted</strong> yükle, link'i başvuruya ver.
                        Veya doğrudan TikTok formuna 60 MB altı dosya yükleyebilirsin.
                    </p>
                </div>
            </div>

            {{-- ═══════════════ ADIM 9 ═══════════════ --}}
            <div class="stg-tt-step">
                <div class="stg-tt-step-num">9</div>
                <div class="stg-tt-step-body">
                    <h6>Submit for Review (Başvuru Gönder)</h6>
                    <ol class="small mb-2">
                        <li>TikTok Developer Console → app detayı → sağ üst <strong>"Submit for review"</strong></li>
                        <li>Form alanları:
                            <ul>
                                <li><strong>App name + category</strong> (Adım 1'den otomatik gelir)</li>
                                <li><strong>Use case</strong> (Adım 7 metni)</li>
                                <li><strong>Demo video URL veya yükle</strong> (Adım 8)</li>
                                <li><strong>Privacy Policy URL:</strong>
                                    <code>{{ url('/gizlilik') }}</code>
                                </li>
                                <li><strong>Terms of Service URL:</strong>
                                    <code>{{ url('/kullanim-sartlari') }}</code>
                                </li>
                                <li><strong>Scope justifications</strong> — her scope için 1-2 cümle</li>
                            </ul>
                        </li>
                        <li><strong>Submit</strong> butonuna bas. Onay email'i 1-2 dakika içinde gelir.</li>
                    </ol>
                    <strong class="small">Scope Justifications (her biri için):</strong>
                    <button type="button" class="btn btn-sm btn-outline-secondary stg-copy-btn mb-2 ms-2"
                            data-copy-target="ttScopesText">
                        <i class="bi bi-clipboard"></i> Kopyala
                    </button>
                    <textarea readonly class="form-control stg-tt-copy-text" id="ttScopesText" rows="6"
                              onclick="this.select()">user.info.basic: To display the connected TikTok username in our admin panel for verification that the correct business account is linked.

video.publish: To automatically publish farm-related video content (Reels) from our admin panel to our own TikTok business account.

video.upload: Required by the Content Posting API to upload video chunks during the publishing process.

photo.publish: To cross-post blog cover images as TikTok Photo Mode slideshows when new blog posts are published on our website.</textarea>
                </div>
            </div>

            {{-- ═══════════════ ADIM 10 ═══════════════ --}}
            <div class="stg-tt-step">
                <div class="stg-tt-step-num">10</div>
                <div class="stg-tt-step-body">
                    <h6>Onay Bekleyişi (2-6 hafta)</h6>
                    <ul class="small mb-2">
                        <li>TikTok email ile aşama aşama bilgilendirir.</li>
                        <li>Sorular gelirse <strong>3-5 gün</strong> içinde cevapla.</li>
                        <li>Reject olursa email'de sebep belirtir → belge düzelt → <strong>Re-submit</strong> (ücretsiz, kaç kez başvurabilirsin).</li>
                    </ul>
                    <div class="alert alert-success small mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        <strong>Beklerken zaman kaybetme:</strong> Aşağıdaki <strong>"Yayın Modu"</strong>
                        <code>Inbox</code> seçili kalsın. Video'lar TikTok app'ine düşer, sen mobilde
                        5 saniyede <strong>"Paylaş"</strong> tıklarsın. Onay gelince
                        <code>Direct Post</code>'a geçer, tam otomatik olur.
                    </div>
                </div>
            </div>

            <hr class="my-4">

            <div class="alert alert-warning small mb-0">
                <i class="bi bi-bookmark-star me-1"></i>
                <strong>En sık reddedilme sebepleri (önceden kontrol et):</strong>
                <ul class="mb-0 mt-1">
                    <li>Privacy policy'de "TikTok" kelimesi geçmiyor → Adım 5 metnini ekledin mi?</li>
                    <li>Demo video TikTok'a paylaşımı göstermiyor → akış net mi?</li>
                    <li>Use case "spam/promotional" olarak görünüyor → "owned account" vurgusu yap (Adım 7 metninde var)</li>
                    <li>Domain doğrulanmamış → Adım 4'ü atla ma</li>
                </ul>
            </div>

        </div>
    </div>
</div>
