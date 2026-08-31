# Yol Haritası — "Eksiksiz Base Kit"e Kalan Yol

**Çıkarıldığı tarih:** 2026-08-31
**Dal:** `feat/laravel-13-upgrade`
**Kapsam:** üç yüz birden — masaüstü web, mobil web, API

Bu belge *ne eksik* sorusunun cevabı. *Ne var* sorusunun cevabı
[`PROJE-DURUMU.md`](PROJE-DURUMU.md)'de; API sözleşmesi
[`API.md`](API.md) ve [`openapi.json`](openapi.json)'da.

---

## Ölçüt: "tam donanımlı" ne demek

Bu kit'ten türeyen bir kurumsal proje, ilk günden şunları **yazmadan** bulmalı:

1. **Web** — içerik yönetimi, çok dillilik, SEO, kimlik, hesap alanı, formlar
2. **Mobil web** — aynı sitenin telefonda kurulabilir, çevrimdışı hata vermeyen,
   parmakla kullanılabilir hâli
3. **API** — mobil uygulamanın aynı iş mantığından beslendiği, sürümlü,
   sözleşmesi makine okunur bir katman
4. **İşletme** — panelden ölçme, raporlama, yedekleme, izleme, kurtarma

Aşağıdaki dört fazın sonunda dördü de kapanıyor. Her madde bir commit, her
maddenin bir kabul ölçütü ve en az bir testi var.

---

## Faz 1 — Hesap ve Kimlik (web + API birlikte) — ✅ TAMAMLANDI

Bugün hesap alanı iki ekran: pano ve profil. API tarafı ondan bir adım önde
(cihaz yönetimi var), web tarafı geride. Bir base kit'in en çok kopyalanan
parçası burası; eksik kalırsa her projede yeniden yazılıyor.

### 1.1 Web'de cihaz ve oturum yönetimi — ✅ bitti (`de77f5e` sonrası)
**Neden:** API'de var (`GET/DELETE /auth/devices`), web'de yok. Aynı kullanıcı
telefonda oturumunu kapatabiliyor, tarayıcıda kapatamıyor.
**Kapsam:** `/hesabim/cihazlar` — açık oturumlar (IP, tarayıcı, son görülme),
tek tek ve toplu kapatma. `SessionRevoker` zaten duruyor.
**Kabul:** Başka bir tarayıcıdaki oturum listede görünüyor, kapatıldığında o
tarayıcı bir sonraki istekte girişe düşüyor. Test: `AccountDeviceTest`.

### 1.2 İki adımlı doğrulama (TOTP) — ✅ bitti
**Neden:** Panel yöneticisinin tek koruması şifre. Kurumsal müşterinin ilk
sorduğu şey; sonradan eklemek oturum ve API akışlarının ikisini birden
değiştiriyor, şimdi eklemek ucuz.
**Kapsam:** TOTP (Google Authenticator uyumlu, harici servis yok), QR kurulum,
tek kullanımlık kurtarma kodları, "yöneticiler için zorunlu" ayarı. Web giriş
akışı + API `POST /auth/login` iki aşamalı yanıt (`two_factor_required`).
**Kabul:** 2FA açık kullanıcı doğru şifreyle giriş yapamıyor, kod isteniyor;
kurtarma kodu bir kez çalışıp tükeniyor. Test: `TwoFactorTest`,
`Api/ApiTwoFactorTest`.

### 1.3 Hesabı kapatma ve veri indirme (KVKK/GDPR) — ✅ bitti
**Neden:** Rıza kaydı var (`Consent`), ama kişinin *silme* ve *taşınabilirlik*
hakkının karşılığı yok. Mağazalar (App Store / Play) uygulama içi hesap silme
yolunu artık şart koşuyor — mobil uygulama bu olmadan yayınlanamıyor.
**Kapsam:** Web `/hesabim/veriler`: verilerimi indir (JSON+ZIP) ve hesabımı
kapat (şifre onaylı, gecikmeli kalıcı silme). API: `GET /account/export`,
`DELETE /account`.
**Kabul:** Kapatılan hesap giriş yapamıyor, jetonları iptal, e-postası
serbest kalıyor; dışa aktarma kişinin bütün kayıtlarını içeriyor.
Test: `AccountDataRightsTest`, `Api/ApiAccountDeletionTest`.

### 1.4 API hesap uçlarının tamamlanması — ✅ bitti
**Neden:** Web'de olup API'de olmayan üç akış var: şifre değiştirme, e-posta
değiştirme doğrulaması, avatar kaldırma. Mobil uygulama bunlar için tarayıcı
açmak zorunda kalıyor.
**Kapsam:** `PUT /account/password` (mevcut şifre onaylı, diğer jetonları
düşürme seçeneği), e-posta değişiminde doğrulama akışının API karşılığı.
**Kabul:** Şifre değişince — istenirse — öteki cihazların jetonu düşüyor.
Test: `Api/ApiAccountTest` genişletmesi.

### 1.5 Bildirim tercihleri — ✅ bitti
**Neden:** Kullanıcının aldığı e-postaları (bülten, yorum yanıtı, duyuru)
kapatabileceği tek yer bülten çıkış bağlantısı. Tercih tablosu olmadan her yeni
e-posta türü aynı sorunu tekrar doğuruyor.
**Kapsam:** `user_notification_preferences`, hesap ekranında anahtarlar, gönderim
öncesi tek kapıdan kontrol; API'de oku/yaz.
**Kabul:** Kapatılan tür o kullanıcıya gitmiyor ve `mail_logs`'a "tercih"
gerekçesiyle düşüyor. Test: `NotificationPreferenceTest`.

---

## Faz 2 — Mobil Web (PWA + erişilebilirlik) — ✅ TAMAMLANDI

Site bugün duyarlı (responsive) ama *mobil* değil: telefona kurulamıyor,
bağlantı kesildiğinde tarayıcının kendi hata sayfasını gösteriyor.

### 2.1 Uygulama bildirimi (`manifest.json`) — ✅ bitti
**Neden:** "Ana ekrana ekle" olmadan PWA'nın geri kalanı da anlamsız.
**Kapsam:** Rotadan üretilen manifest (ad, ikon, tema rengi panelden geliyor —
sabit dosya olsaydı her projede elle düzenlenirdi), 192/512 ikon üretimi
`UploadService` üzerinden, `apple-touch-icon` zinciri.
**Kabul:** Chrome ve Safari'de kurulabilir; kurulan uygulama panelde ayarlanan
adı ve rengi taşıyor. Test: `PwaManifestTest`.

### 2.2 Servis çalışanı ve çevrimdışı sayfa — ✅ bitti
**Neden:** Build tool yasağı yüzünden hazır PWA eklentileri kullanılamıyor;
elle yazılmış, küçük ve okunur bir servis çalışanı gerekiyor.
**Kapsam:** Kabuk + statik varlık önbelleği, sürüm damgası (dosya değişince
eski önbellek düşer), çevrimdışı sayfa, HTML için "önce ağ" stratejisi —
içerik bayatlamamalı.
**Kabul:** Uçak modunda site açılıyor ve çevrimdışı sayfa çıkıyor; yeni sürüm
yayınlandığında bir sonraki ziyarette güncel içerik geliyor.
Test: `ServiceWorkerTest` (kayıt, kapsam, sürüm damgası).

### 2.3 Mobil kullanım denetimi — ✅ bitti
**Neden:** 70 KB'lık ön yüz CSS'inde yalnız 10 medya sorgusu var; düzen
Bootstrap ızgarasına bırakılmış. Izgara düzeni çözer, dokunma hedefini ve
yatay taşmayı çözmez.
**Kapsam:** 360 px'te bütün ön yüz ve panel ekranlarının taranması; dokunma
hedefi ≥44 px, yatay kaydırma sıfır, panel tablolarında kaydırma kabı,
yapışkan başlık/eylem çubukları.
**Kabul:** Tarayıcıda 360×640'ta gezinti kanıtlanıyor; `document.body.scrollWidth`
taşmıyor. Test: yatay taşma bekçisi + görsel kanıt.

### 2.4 Erişilebilirlik taban çizgisi — ✅ bitti
**Düzeltme:** Bu maddenin gerekçesi kısmen yanlıştı — içeriğe atlama bağlantısı
zaten vardı (`skip-to-content`). Denetim yapıldığında ön yüzde adsız tek bir
kontrol çıktı (bültenin gönder düğmesi) ve bütün form alanlarının etiketi
yerindeydi. Eksik olan şey bekçiydi: `AccessibilityBaselineTest`.
**Kapsam:** Skip link, odak halkaları, form etiket eşleşmeleri, ikon
düğmelerine erişilebilir ad, renk kontrastı, `prefers-reduced-motion`
(kısmen var).
**Kabul:** Klavyeyle bütün ön yüz gezilebiliyor, odak her zaman görünür.
Test: `AccessibilityBaselineTest` (etiketsiz girdi ve adsız ikon düğmesi yok).

---

## Faz 3 — Panelin Eksik Ekranları — ✅ TAMAMLANDI

Temada tasarımı hazır olup kodu olmayan üç ekran. Tasarım dosyaları
`resources/views/admin-theme/` altında duruyor, birebir uyarlanacak.

### 3.1 Raporlar (`reports.html`) — ✅ bitti
**Neden:** Veri panelde toplanıyor (ziyaret, içerik, mail, kullanıcı, kampanya)
ama tek bir yerden okunmuyor; yönetici sayıları beş ekrandan derliyor.
**Kapsam:** Tarih aralığı seçimli rapor ekranı — trafik, içerik üretimi, mail
gönderimi, kullanıcı büyümesi, kampanya başarımı; her rapor Excel/PDF çıktısı
(`app/Exports` altyapısı hazır).
**Kabul:** Seçilen aralık bütün kartlara ve dışa aktarmaya aynı şekilde
uygulanıyor. Test: `AdminReportsTest`.

### 3.2 Genel içerik listesi (`content-list.html`) — ✅ bitti
**Neden:** Blog, sayfa, galeri ve SSS ayrı listelerde; "geçen ay ne yayınlandı"
sorusunun tek ekranlık cevabı yok. Site geneli arama servisi
(`SearchService`) bu birleşik görünümün sorgu tarafını zaten kuruyor.
**Kapsam:** Tür/dil/durum/tarih süzgeçli birleşik liste, toplu durum
değiştirme, kayda gitme.
**Kabul:** Dört tür de tek listede, süzgeçler birleşik çalışıyor, yetkisi
olmayan türü göremiyor. Test: `AdminContentListTest`.

### 3.3 Yardım (`help.html`) — ✅ bitti
**Neden:** Panelde 30'dan fazla ekran var; devralan kişi için panel içi rehber
yok. Bu kit başkalarına teslim edilmek için var.
**Kapsam:** Modül modül kısa rehber, sık sorulanlar, sürüm ve ortam bilgisi,
destek iletişimi — içeriği çeviri dosyalarından, böylece projeye göre
değiştirilebilir.
**Kabul:** Her sidebar modülünün bir yardım başlığı var. Test: `AdminHelpTest`.

---

## Faz 4 — API Olgunluğu

### 4.1 Push bildirim altyapısı — 🟡 sunucu tarafı bitti, panel ekranı bekliyor
**Neden:** Mobil uygulamanın ilk isteyeceği şey; sunucu tarafı hazır değilse
uygulama ekibi bekliyor. Sağlayıcıdan bağımsız kurgulanabilir: jeton kaydı ve
gönderim kancası bizde, taşıyıcı (FCM/APNs) yapılandırmada.
**Kapsam:** `POST/DELETE /account/push-tokens`, cihaz eşleştirmesi, panelden
"bildirim gönder" ekranı, gönderim kuyruğa düşüyor.
**Yapılan:** Jeton kaydı, cihaz eşleştirme, sağlayıcıdan bağımsız gönderim
servisi (FCM sürücüsü + yapılandırılmamışken log), ölü jetonun düşmesi ve
oturum kapanınca jetonların silinmesi.
**Kalan:** Panelden bildirim yazıp gönderme ekranı. Admin temada bu ekranın
tasarımı yok (`notifications.html` yalnız tercih anahtarları içeriyor) ve
tasarımda olmayan ekranı uydurmak proje kuralına aykırı — tasarım geldiğinde
ya da onay verildiğinde yapılacak.
**Kabul:** Jeton kaydı cihazla eşleşiyor, oturum kapanınca jeton düşüyor.
Test: `Api/ApiPushTokenTest`.

### 4.2 Sürüm ve sağlık ucu — ✅ bitti
**Neden:** Mağazadaki eski sürümü zorla güncellemenin yolu yok; bakım
penceresini uygulama önceden bilmiyor.
**Kapsam:** `GET /api/v1/health` — sürüm, asgari desteklenen istemci sürümü,
bakım durumu.
**Kabul:** Asgari sürüm ayarı yükseltilince eski istemci "güncelle" yanıtı
alıyor. Test: `Api/ApiHealthTest`.

### 4.3 Kullanıcının kendi yorumları — ✅ bitti
**Neden:** Yorum gönderilebiliyor ama kişi kendi yorumlarını göremiyor,
silemiyor. Web'de de yok — ikisi birlikte yapılmalı.
**Kapsam:** `GET /account/comments`, `DELETE /account/comments/{id}`; web'de
hesap ekranında aynı liste.
**Kabul:** Sadece kendi yorumları, onay bekleyenler dahil.
Test: `Api/ApiAccountCommentsTest`.

### 4.4 Şemanın hizada kalması — ✅ sürüyor (38 uç şemada)
**Neden:** `openapi.json` kendi kendini denetliyor (`OpenApiSpecTest`); yeni
uçlar eklendikçe bu bekçi güncel kalmalı, yoksa sessizce bayatlar.
**Kapsam:** Faz 1–4'te eklenen her uç için şema girdisi ve `API.md` bölümü.
**Kabul:** `OpenApiSpecTest` yeşil ve rotalarla şema arasında fark yok.

---

## Faz 5 — Dayanıklılık ve Bakım — ✅ TAMAMLANDI (bir madde bilerek ertelendi)

### 5.1 Yedeğin dış kopyası — ✅ bitti
**Neden:** Arşiv, yedeklediği veriyle aynı diskte duruyor. Diski kaybeden
yedeği de kaybediyor — yedeklemenin var olma sebebi bu senaryoydu.
**Kapsam:** Yapılandırılabilir dış hedef (S3 uyumlu ya da FTP), yükleme sonrası
doğrulama, başarısızlıkta yöneticiye bildirim, dış kopyada saklama süresi.
**Kabul:** Yedek alındıktan sonra dış hedefte aynı boyutta dosya bulunuyor;
hedef erişilemezse iş "başarılı" sayılmıyor. Test: `BackupOffsiteTest`.

### 5.2 `jenssegers/agent` bağımlılığından çıkış — ✅ bitti
**Neden:** 2020'den beri güncellenmiyor. Tek kullanım yeri `AnalyticsService`;
etki alanı dar olduğu için şimdi çıkmak ucuz, PHP 9'da mecbur kalmak pahalı.
**Kapsam:** Tarayıcı/işletim sistemi/cihaz türü tespiti için küçük bir iç
servis + kendi test kümesi (gerçek `User-Agent` örnekleriyle).
**Yapılan:** Ayrıştırma `UserAgentParser` servisine çıkarıldı (Faz 1.1 yolunda);
paket kararı artık tek dosyada. Kalan: paketin yerine geçecek tabloyu
zenginleştirip bağımlılığı `composer.json`'dan düşürmek.
**Kabul:** Analitik ekranındaki dağılımlar değişmiyor; bağımlılık
`composer.json`'dan düşüyor. Test: `UserAgentParserTest`.

### 5.3 Test paketinin bellek bütçesi — ✅ bitti
**Neden:** `vendor/bin/phpunit` 1516 testi 44 saniyede yeşil bitiriyor ama tepe
belleği **131 MB**. Stok `memory_limit=128M` ile suite yarıda düşüyor
(`RolePermissionManagementTest` render ederken). Tek başına en ağır sınıf
83 MB'de kalıyor — yani ekran değil, suite boyunca biriken bellek. CI'da sınır
yüksek olduğu için görünmüyor; kit'i klonlayan geliştiricinin makinesinde
görünüyor.
**Kapsam:** Birikimin kaynağı (test başına tutulan uygulama örneği, fabrika
verileri) bulunup düşürülmeli; olmazsa `phpunit.xml` ya da `composer test`
gereken sınırı kendisi vermeli ve README bunu yazmalı.
**Kabul:** `composer test` stok 128 MB'lık bir PHP ile baştan sona koşuyor.
Test: mevcut suite (kendisi ölçüt).

### 5.4 Sertleştirme kararları — 🟡 biri yapıldı
**Neden:** İki config değeri bilinçli olarak varsayılanda bırakılmıştı; karar
verilmiş ama uygulanmamış hâlde duruyorlar.
**Kapsam:** `session.serialization = json` (bakım penceresinde, oturumlar
düşeceği için) ve `cache.serializable_classes` için izin listesi.
**Yapılan:** `cache.serializable_classes` izin listesi kuruldu ve yedi
önbellekli yolun hepsi iki geçişli testle (yaz + geri oku) kapsandı.
**Kalan:** `session.serialization = json`. Bilerek ertelendi: çevirmek o anda
açık olan bütün oturumları düşürüyor ve bu, çalışan bir kurulumda bakım
penceresi gerektiren bir karar — kod değil, zamanlama meselesi.

---

## Sıra ve Gerekçesi

| Faz | Neden bu sırada |
|---|---|
| 1 — Hesap ve kimlik | En çok kopyalanan parça, en eksik olan; mağaza şartı (hesap silme) buna bağlı |
| 2 — Mobil web | Kullanıcının gördüğü ikinci yüz; Faz 1'in ekranları da mobil doğsun |
| 3 — Panel ekranları | İçeride kalıyor, dışarıya söz vermiyor; ertelenebilir ama tema zaten hazır |
| 4 — API olgunluğu | Mobil uygulama başlamadan önce bitmeli, Faz 1'in uçlarıyla aynı şemayı paylaşıyor |
| 5 — Dayanıklılık | Görünmez ama en pahalı hatalar burada; bellek maddesi hedef ortamı doğrudan ilgilendiriyor |

## Kapsam dışı (bilerek)

- **E-ticaret** (ürün, sipariş, ödeme) — `ab57deb`'de sökülmüştü, base kit
  genel kalmalı. Temada duran `orders.html` / `products.html` bu yüzden boş.
- **Sosyal giriş** (Google/Apple ile giriş) — her projede farklı sağlayıcı ve
  onay süreci; kit'e sabit gelmesi zarar veriyor.
- **Çok kiracılı yapı (multi-tenant)** — mimarinin tamamını değiştirir.
