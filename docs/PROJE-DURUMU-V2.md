# Proje Durumu — v2 Denetimi

> **Not.** Bu belge artık [`PROJE-KAYDI.md`](PROJE-KAYDI.md) içinde de
> bulunuyor — dört durum belgesinin tek dosyada toplandığı, güncel durum
> tablosunu ve kalan iş planını taşıyan kayıt. Bu dosya kaynak olarak yerinde
> duruyor ve içeriği değişmedi.

**Tarih:** 2026-09-01
**Dal:** `feat/laravel-13-upgrade`
**Başlangıç noktası:** `f1eca0c`
**Kapsam:** üç yüz birden — web, panel (CRM), mobil API

Bu belge bir *denetimin* kaydı. [`PROJE-DURUMU.md`](PROJE-DURUMU.md) *ne var*
sorusunu, [`YOL-HARITASI.md`](YOL-HARITASI.md) *ne eksik*,
[`BOSLUK-ANALIZI.md`](BOSLUK-ANALIZI.md) *hangi sırayla* sorusunu yanıtlıyor.
Buradaki soru üçüncüsü: **yapıldı denilen şeyler gerçekten çalışıyor mu, ve
kimsenin bakmadığı yerde ne birikmiş?**

---

## Yöntem

Denetim üç aşamada koştu:

1. **Doğrulama.** Var olan 1711 test, kod stili (Pint) ve statik analiz
   (PHPStan) çalıştırıldı — üçü de temiz. Yani dokümanda "bitti" yazan
   maddelerin kod karşılığı gerçekten duruyor.
2. **Arama.** Doğrulama yeşil olduğu için asıl soru şuna dönüştü: *hangi kural
   yazılı ama bekçisiz, hangi modül kodlanmış ama sınanmamış?* Aranan şey kırık
   kod değil, **görünmeyen kod** oldu.
3. **Kapatma.** Bulunan her kusur düzeltildi ve her biri için bir bekçi kuruldu
   — düzeltmenin kendisi değil, bir daha açılmaması aranan sonuçtu.

Bulguların ortak deseni şu çıktı: **kural yazılıydı, bekçi ya yoktu ya da elle
yazılmış bir listeye bakıyordu.** Elle yazılan liste, projenin geri kalanı
büyürken yerinde kalıyor; kural teknik olarak yürürlükte ama pratikte
uygulanmıyor. On altı bulgunun on ikisi bu boşlukta birikmişti.

---

## Özet tablo

| # | Bulgu | Ağırlık | Durum |
|---|---|---|---|
| 1 | Dışa aktarmada CSV biçimi yoktu | Yüksek | ✅ Kapatıldı |
| 2 | Üç liste ekranında dışa aktarma hiç yoktu | Yüksek | ✅ Kapatıldı |
| 3 | Dışa aktarma modülünün hiçbir testi yoktu | Yüksek | ✅ 81 test |
| 4 | Zamanlanmış raporlarda CSV seçilemiyordu | Orta | ✅ Kapatıldı |
| 5 | Editörün dosya seçicisi boş kurulumda 404 veriyordu | Yüksek | ✅ Kapatıldı |
| 6 | Kuralsız alan bekçisi panelin hiçbir formunu görmüyordu | Yüksek | ✅ Kapatıldı |
| 7 | Bekçinin tarayıcısı nitelikleri yanlış okuyordu | Orta | ✅ Kapatıldı |
| 8 | Satır içi stil yasağının bekçisi yoktu; 13 ihlal birikmişti | Orta | ✅ Kapatıldı |
| 9 | Rol matrisi 12 modül geride kalmıştı | Yüksek | ✅ Kapatıldı |
| 10 | Panel duman testi 26 rotaya bakıyordu (55 ekran var) | Orta | ✅ Kapatıldı |
| 11 | Ön yüzün duman testi hiç yoktu | Orta | ✅ Kapatıldı |
| 12 | Çeviri eşliği yalnız `site.php`'de sınanıyordu | Orta | ✅ Kapatıldı |
| 13 | `lang/tr/validation.php` dokuz kuralı taşımıyordu | Orta | ✅ Kapatıldı |
| 14 | Profil ekranında tarayıcının `alert()` kutusu | Düşük | ✅ Kapatıldı |
| 15 | Çerez rızası kutuları kuralsız ve işaretsizdi | Düşük | ✅ Kapatıldı |
| 16 | Stok config dosyalarında `strict_types` yoktu | Düşük | ✅ Kapatıldı |

**Sonuç:** 1711 → **1834 test** (1827 geçiyor, 7'si gerekçeli olarak atlanıyor),
6303 → **7408 doğrulama**. Pint sıfır sapma, PHPStan sıfır hata.

Bu tablo v2 denetiminin kendi bulgularını sayıyor. Turun sonunda ayrıca 31
Ağustos tarihli boşluk analizinin açık kalan dört bulgusu da kapatıldı —
aşağıda *Tur 3* bölümünde.

---

## 1. Dışa aktarma — CSV yoktu

**Hata.** Modül Excel (XLSX) ve PDF yazıyordu; CSV yoktu. Bu bir biçim eksiği
gibi görünse de aslında bir *kullanım* eksiği: XLSX ve PDF insanın okuduğu
biçimler, CSV ise verinin başka bir sisteme taşındığı biçim — muhasebe
programına, e-posta aracına, bir betiğe. Kit'ten türeyen her projede er ya da
geç isteniyor ve olmadığında herkes kendi çözümünü yazıyor.

**Çözüm.** `CsvExportService` eklendi; XLSX yazıcısıyla aynı sözleşmeyi
(`ListExport`) okuyor, yani bir liste tanımı üç biçimde birden çalışıyor.

Türkçe Excel'i hedefleyen iki ayar yapılandırmadan geliyor:

- **UTF-8 BOM** — dosyanın başındaki imza olmadan Excel Türkçe harfleri sistemin
  kod sayfasıyla açıyor ve bozuyor.
- **Noktalı virgül ayracı** — ondalık ayracı virgül olan yerel ayarlarda Excel,
  virgülle ayrılmış dosyayı tek sütuna basıyor.

Başka yerel ayara kurulan bir projede üçü de `.env` üzerinden çevrilebiliyor
(`EXPORT_CSV_DELIMITER`, `EXPORT_CSV_DECIMAL_SEPARATOR`, `EXPORT_CSV_BOM`).

**Yol üzerinde bulunan iki şey:**

- Formül enjeksiyonu koruması (`=CMD(...)` gibi bir metnin hücrede formüle
  dönmesi) yalnız XLSX yazıcısında vardı. CSV'de tehlike daha büyük — orada tip
  diye bir şey yok, her hücre metin gidiyor ve açan program ne olduğuna kendi
  karar veriyor. Koruma ortak bir trait'e çıkarıldı, iki yazıcı da aynı kuralı
  uyguluyor.
- Satır tavanı kontrolü denetleyicide `format === Pdf` diye yazılıydı. Tavanı
  olan biçim gerçekten yalnız PDF (mPDF sayfaları belge kapanana kadar bellekte
  tutuyor; XLSX ve CSV akış hâlinde yazılıyor) ama bu bilgi biçimin kendisine
  ait. `ExportFormat::hasRowLimit()` olarak taşındı: yeni bir biçim
  eklendiğinde denetleyiciye dokunmak gerekmiyor.

**Değişen dosyalar**

| Dosya | Ne oldu |
|---|---|
| `app/Services/Export/CsvExportService.php` | yeni — CSV yazıcısı |
| `app/Services/Export/Concerns/GuardsSpreadsheetFormulas.php` | yeni — ortak formül koruması |
| `app/Support/Export/ExportFormat.php` | `Csv` durumu, `hasRowLimit()` |
| `app/Services/Export/ExportService.php` | CSV bağlandı, tavan biçime soruluyor |
| `app/Services/Export/ExcelExportService.php` | formül koruması trait'e devredildi |
| `app/Http/Controllers/Admin/ExportController.php` | biçime duyarlı tavan kontrolü |
| `config/export.php` | CSV ayarları |
| `resources/views/components/export-menu.blade.php` | menüye CSV satırı |

---

## 2. Üç liste ekranında dışa aktarma hiç yoktu

**Hata.** Panelde 29 liste dışa aktarılabiliyordu, üçü aktarılamıyordu:

- **İçerikler** (`/admin/icerikler`) — blog, sayfa, galeri ve SSS'nin birleşik
  listesi. "Geçen ay ne yayınladık" sorusunun tek ekranlık cevabı, ama dosyaya
  dökülemiyordu.
- **Özel Adresler** (`/admin/custom-routes`) — hangi adresin nereye baktığı.
  Bir siteyi devralan ekibin ilk sorduğu şey ve çoğu zaman panelin dışında
  (yönlendirme planı, SEO denetimi) okunuyor.
- **Başarısız İşler** (`/admin/kuyruk`) — bu listenin kaybı en pahalısı: kayıt
  kalıcı değil, iş yeniden denendiğinde ya da tablo temizlendiğinde siliniyor.
  Dosya, tabloyu boşaltmadan önce elde kalan tek kayıt oluyor.

**Çözüm.** Üç liste tanımı yazıldı ve `config/export.php`'ye kaydedildi;
ekranlarına dışa aktarma menüsü eklendi. İki servise okuma ucu açıldı:

- `ContentListService`: sıralı sorgu `rows()` ve `count()` olarak ayrıldı,
  `paginate()` de artık onları kullanıyor — ekranda görünen ile dosyaya inen
  aynı sorgunun ürünü, zamanla ayrışamazlar.
- `QueueMonitorService`: `countFailed()` ve `eachFailedChunk()`. Satırlar
  ekrandakiyle aynı sunumdan geçiyor, yani dosyada `SendQueuedMailable` değil,
  gerçekte patlayan işin adı duruyor.

**Değişen dosyalar:** `app/Exports/ContentListExport.php`,
`app/Exports/CustomRouteExport.php`, `app/Exports/FailedJobExport.php` (yeni),
`app/Services/ContentListService.php`, `app/Services/QueueMonitorService.php`,
`config/export.php`, üç `index.blade.php`.

---

## 3. Modülün hiçbir testi yoktu

**Hata.** 1711 testlik bir projede dışa aktarma modülü — 29 liste tanımı, iki
yazıcı, yetki kontrolü, denetim kaydı, satır tavanı — **tek testle bile
kapsanmıyordu.** (Tek istisna raporlar ekranının Excel çıktısıydı.)

Bu, modülün doğası yüzünden özellikle tehlikeli: bir liste tanımındaki hata —
yanlış sütun kapanışı, olmayan bir ilişki, sorgusuz bırakılmış bir liste — ancak
dosya üretilirken patlıyor. Ekranda hiçbir şey görünmüyor; kullanıcı indirmeye
bastığında 500 alıyor.

**Çözüm.** `ListExportTest` — 81 test (74 koşuyor, 7'si arkasında tablo olmayan
listeler için gerekçeli olarak atlanıyor). Sınav tek tek listeler üzerinden değil,
**kayıt defterinin tamamı** üzerinden koşuyor: `config/export.php`'ye yeni bir
liste eklendiği anda o liste de kapsama giriyor, ayrıca test yazmak gerekmiyor.

Kapsanan sorular:

- Kayıtlı 32 listenin her biri, üç biçimde de dosya üretiyor mu?
- Üretilen dosya iddia ettiği biçimde mi? (imza denetimi: `PK` → XLSX,
  `%PDF` → PDF, BOM → CSV)
- Yetkisiz kullanıcı indirebiliyor mu? Oturum açmamış ziyaretçi?
- Ekrandaki süzgeçler dosyaya yansıyor mu? Sayfa numarası süzgeç sanılıyor mu?
- PDF tavanı aşıldığında dosya sessizce kırpılıyor mu, kullanıcı uyarılıyor mu?
- Excel ve CSV aynı tavandan etkileniyor mu? (etkilenmemeli)
- Her indirme denetim kaydına düşüyor mu?
- Ekranların gösterdiği her anahtar kayıt defterinde var mı?
- **Dışa aktarma satır başına sorgu atıyor mu?**

Son madde ayrı bir not hak ediyor. Listelerin en sessiz tehlikesi bu: ekranda
yirmi beş satır varken ilişkiye dokunan bir sütun fark edilmiyor; aynı sütun on
bin satırlık dosyada on bin sorgu açıyor ve istek zaman aşımına düşüyor. Sınav
sayıya değil davranışa bakıyor — kayıt sayısı artınca sorgu sayısı artmamalı.
**25 listede N+1 yok**; kalan yediden altısı arkasında tablo olmayan listeler
(yedekler diskten, sistem durumu canlı ölçümden okuyor), biri de tek bir üst
kayda bağlı.

Ölçüm iki kez yanlış alarm verdi ve ikisi de öğreticiydi:

1. İlk istek ayarları ve dilleri önbelleğe alıyor; o sorgular listeye değil
   kuruluma ait. Ölçüm ısınmış durumdan başlamalı.
2. Kayıt eklemek ilgili önbellekleri düşürüyor (yönlendirme haritası gibi); o
   bir kerelik tazeleme satır sayısına bağlı değil. Kayıtlar eklendikten sonra
   ikinci bir ısıtma gerekiyor.

**Değişen dosya:** `tests/Feature/ListExportTest.php` (yeni, 81 test).

---

## 4. Zamanlanmış raporlarda CSV seçilemiyordu

**Hata.** Rapor zamanlama formu yalnız Excel ve PDF sunuyordu; `FormRequest`
kuralı da `Rule::in(['excel', 'pdf'])` ile bunu sabitliyordu. İlginç ayrıntı:
panel temasının CSS'inde `.rpr-format-badge.csv` sınıfı **zaten duruyordu** —
tasarım CSV'yi öngörmüş, kod eklememişti.

**Çözüm.** Biçim seçeneğine CSV eklendi, sunucu kuralı genişletildi, rapor
kartlarındaki biçim rozetlerine CSV eklendi (tasarımın hazır sınıfıyla).
Gönderim yolu zaten biçimden bağımsızdı — `ReportScheduleService` dosyayı
`ExportService::store()` ile üretiyor, yani ekranda inen dosya ile postayla
gelen dosya aynı kodun ürünü.

**Değişen dosyalar:** `resources/views/admin/reports/index.blade.php`,
`app/Http/Requests/Admin/StoreReportScheduleRequest.php`.

---

## 5. Editörün dosya seçicisi boş kurulumda 404 veriyordu

**Hata.** `FileBrowserService::resolve()` yükleme kökünü `realpath()` ile
çözüyor, çözemezse istisna fırlatıyordu; denetleyici bunu 404'e çeviriyordu.
Kök dizin ise henüz hiçbir şey yüklenmemiş taze bir kurulumda var olmayabilir —
git boş dizin taşımıyor ve yol `.env` üzerinden başka bir yere de bakabiliyor.

Sonuç: kit'i klonlayan geliştirici zengin metin editöründe "Görsel seç"e
bastığında hata alıyor, bir arıza sanıyor. Oysa ortada yalnızca boş bir kurulum
var.

Bu kusuru bulan şey, panelin genişletilmiş duman testi oldu (bkz. 10): eski
liste `/admin/dosya-secici` adresini hiç açmıyordu.

**Çözüm.** Kök dizin gerektiğinde açılıyor. Yükleme de aynı köke yazacağı için
bu, sonraki ilk yüklemenin yapacağı işi öne almaktan ibaret.

**Değişen dosya:** `app/Services/FileBrowserService.php`.

---

## 6–7. Kuralsız alan bekçisi panelin hiçbir formunu görmüyordu

**Hata.** Proje kuralı net: *kullanıcının veri girdiği her alan ya
`data-validation-engine` taşır ya da bilerek boş bırakıldığını söyleyen
`data-fv-ignore` taşır.* Bekçisi de vardı — ama **elle yazılmış on ön yüz
görünümüne** bakıyordu. Panelin doksana yakın formu hiç taranmıyordu ve yeni bir
ekranın kapsama girmesi, o listeye elle satır yazmaya bağlıydı.

**İkinci hata, birincisinden ilginç:** bekçinin etiket tarayıcısı nitelikleri
yanlış okuyordu. İki yerde kırılıyordu:

```html
<textarea placeholder="<iframe src='...'></iframe>" data-validation-engine="...">
<input @checked($tercihler[$tur->value]) data-fv-ignore>
```

Birincisinde `placeholder` değerinin içindeki `>`, ikincisinde
`$tur->value` ifadesindeki ok işareti etiketi erken kapatıyor; ondan sonraki
nitelikler — kuralın kendisi dahil — görünmez oluyordu. Yani bekçi hem kural
taşıyan alanı kuralsız sanabiliyor, hem de kuralsız bir alanı hiç görmeyebiliyordu.

**Çözüm.** `FormFieldsCarryRulesTest` — liste yok, görünüm ağacının tamamı
taranıyor (`admin-theme`, `emails` ve `vendor` gerekçeleriyle dışarıda).
Tarayıcı düzeltildi: nitelik değerleri tırnak içinde okunuyor ve nitelik yerine
geçen Blade direktifleri maskeleniyor. Taramanın kendisi de sınanıyor — iki ayrı
test, iki kırılma biçimini tekrar üretip tarayıcının artık doğru okuduğunu
gösteriyor.

Ön yüz testindeki dar kopya kaldırıldı; yerine yeni bekçiye gönderen bir not
bırakıldı.

**Tarama sonucu:** panelin doksana yakın formunda **tek bir kuralsız alan
yoktu** — kural gerçekten uygulanmış, yalnız kanıtı eksikmiş. Ön yüzde bir
tane çıktı (bkz. 15).

**Değişen dosyalar:** `tests/Feature/FormFieldsCarryRulesTest.php` (yeni),
`tests/Feature/FrontFormInputRulesTest.php`.

---

## 8. Satır içi stil yasağının bekçisi yoktu

**Hata.** Kural yazılıydı (*inline style yasak, her zaman class kullan*) ama
bekçisi yoktu ve görünümlerde **on üç yerde** satır içi stil birikmişti — biri
iki ayrı ekrana kopyalanmış aynı biçimdi.

Böyle biriken stiller tasarımı tek yerden değiştirilemez hâle getiriyor: aynı
kutunun rengi ekranın birinde değişiyor, ötekinde kalıyor. Bir örnek doğrudan
buydu — bildirim açılır listesinin ölçüleri hem `.nt-dropdown` sınıfında hem de
etiketin üstünde duruyordu.

**Çözüm.** On üç biçim de CSS'e taşındı, **değerler birebir korunarak**. Bootstrap'in
birebir karşılığı olan ikisi (`w-auto`, `object-fit-cover`) hazır sınıfa
bağlandı; kalanı için `styles.css`'e adı anlamlı sınıflar eklendi.

Çalışma anında hesaplanan tek değer — kampanya ilerleme çubuğunun doluluğu —
sabit bir sınıfla anlatılamaz. O, projede zaten kullanılan kalıba çevrildi:
etikete giden şey biçim değil, tek bir CSS değişkeni (`--cmp-progress`); genişliği
stil sayfası okuyor.

**Bekçi.** `InlineStylesAreForbiddenTest`. İki durum kapsam dışı ve ikisi de
kaçınılabilir değil: e-posta gövdeleri (posta istemcileri harici stili atıyor)
ve yalnız CSS değişkeni taşıyan bildirimler. Tek tek istisna da var — panelin
stil sayfasının ulaşamadığı bir `iframe` belgesine yazılan yedek metin — ve
istisna listesinin bayatlamaması ayrıca sınanıyor.

**Değişen dosyalar:** `public/assets/admin/css/styles.css`, on bir görünüm,
`tests/Feature/InlineStylesAreForbiddenTest.php` (yeni).

---

## 9. Rol matrisi on iki modül geride kalmıştı

**Hata.** Yetki matrisi 24 rota kapsıyordu; panelde 33 modül ekranı var.
Kapsam dışı kalanlar arasında **sistem ayarlarına dokunan ekranlar** vardı:
diller, dil yazıları, kuyruk izleyici, özel adresler. Hiçbirinin rol davranışı
sınanmıyordu — biri yanlışlıkla editöre açılsa kimse görmezdi.

**Çözüm.** Önce eksik on iki modülün gerçek davranışı ölçüldü, sonra matrise
yazıldı. Ölçüm tutarlı çıktı ve mevcut yetki tasarımını doğruladı:

| Alan | Admin | Editör | Moderatör |
|---|---|---|---|
| Yardım (panel içi rehber) | ✅ | ✅ | ✅ |
| İçerik: kategoriler, birleşik liste, dosya seçici, raporlar | ✅ | ✅ | ⛔ |
| Pazarlama: aboneler, kampanyalar | ✅ | ✅ | ⛔ |
| Sistem: diller, dil yazıları, kuyruk, özel adresler | ✅ | ⛔ | ⛔ |

**Bekçi.** Matris elle yazılıyor — hangi rolün neyi göreceği bir iş kararı,
koddan çıkarılamaz. *Kapsamının tam olması* ise koddan çıkarılabilir:
`test_the_matrix_covers_every_module_in_the_panel` panele yeni bir modül
eklendiğinde kırmızı oluyor ve kararın verilmesini zorluyor.

**Değişen dosya:** `tests/Feature/AdminAuthorizationTest.php` (24 → 35 rota,
+1 kapsam bekçisi).

---

## 10–11. Duman testleri

**Hata.** Panelin duman testi elle yazılmış **26 rotaya** bakıyordu; parametresiz
admin ekranı sayısı **55**. Aradaki fark — kuyruk, raporlar, içerikler, diller,
çeviriler, kampanyalar, yardım, özel adresler, dosya seçici — hiç açılmadan
kalıyordu. Ön yüzün duman testi ise **hiç yoktu**: bir sayfanın açıldığını
doğrulayan tek şey, o sayfaya ait modül testinin varlığıydı.

Bu boşluğun bedeli somut çıktı: 5 numaralı kusur (dosya seçicisi 404) tam olarak
buradan bulundu.

**Çözüm.**

- `AdminSmokeTest` artık rota tablosundan besleniyor: parametresiz her admin GET
  rotası geziliyor, **ayrıca hepsinin oturum açmamış ziyaretçiye kapalı olduğu**
  doğrulanıyor. Bu ikincisi ince bir riski kapatıyor: tek bir rotanın ara katman
  grubunun dışında tanımlanması yetiyor ve o ekran herkese açık kalıyor.
- `FrontSmokeTest` yeni. Herkese açık sayfalar (ana sayfa, blog, galeri,
  iletişim, arama, SSS, besleme, giriş/kayıt akışları, çevrimdışı sayfası,
  `robots.txt`, `sitemap.xml`, manifest, servis çalışanı) ve hesap alanının
  bütün ekranları — hem üyeye açık hem ziyaretçiye kapalı olduğu.

Ayrıca Laravel'in stok `ExampleTest` dosyaları kaldırıldı; biri
`assertTrue(true)` idi, yani hiçbir şey sınamıyordu.

**Değişen dosyalar:** `tests/Feature/AdminSmokeTest.php`,
`tests/Feature/FrontSmokeTest.php` (yeni), iki `ExampleTest.php` (silindi).

---

## 12–13. Çeviri eşliği ve eksik doğrulama metinleri

**Hata.** İki dilin aynı anahtarları taşıdığını sınayan bekçi vardı ama yalnız
`site.php`'ye bakıyordu. `validation.php` ve `api.php` kapsam dışıydı — ve
`lang/tr/validation.php`, Laravel 13 ile gelen **dokuz kuralı** taşımıyordu:

`any_of`, `array_keys`, `base64`, `doesnt_contain`, `encoding`,
`in_array_keys`, `prohibited_if_accepted`, `prohibited_if_declined`, `custom`

Bu kurallardan biri kullanıldığında Türkçe sayfada mesaj yerine anahtarın
kendisi görünecekti: `validation.base64`.

**Çözüm.** Dokuz karşılık Türkçe dosyaya eklendi. Bekçi iki dilde de bulunan
bütün dosyalara genişletildi; boş değer sınavı da öyle. Yalnız bir dilde olan
dosyalar kasıtlı ve gerekçeleri dosyaların içinde yazılı: `auth.php`,
`passwords.php` ve `pagination.php` Türkçede var çünkü yedek dil Türkçe ve
dosya olmadığında anahtarın kendisi görünüyordu; İngilizcede çerçevenin kendi
dosyaları devreye giriyor.

**Değişen dosyalar:** `lang/tr/validation.php`,
`tests/Feature/InterfaceTranslationTest.php`.

---

## 14–16. Küçük kusurlar

**Tarayıcının `alert()` kutusu.** Profil ekranında 2 MB üstü avatar seçilince
`alert()` çıkıyordu — panelin kendi kutusu (`AdminModal`) varken ve proje kuralı
bunu yasaklarken. Değiştirildi; `AdminModal` yüklenmemişse işlem yine
durduruluyor, sadece sessizce.
→ `public/assets/admin/js/profile.js`

**Çerez rızası kutuları.** Ne kural ne de "bilerek boş" işareti taşıyorlardı —
6 numaralı bekçinin ön yüzde bulduğu tek gerçek eksik. Seçim kutusu oldukları
için doğrulanacak bir uzunluk yok; gelen değerlerin tanınan kategoriler olduğunu
sunucu söylüyor (`StoreConsentRequest`). `data-fv-ignore` gerekçesiyle eklendi.
→ `resources/views/partials/cookie-consent.blade.php`

**`strict_types`.** Projenin kendi yazdığı her PHP dosyasında vardı; Laravel'in
stok config dosyalarında (10 dosya) yoktu. Eklendi.
→ `config/*.php`

---

## Denetlenip temiz çıkanlar

Bir denetimin yarısı da bulunmayan şeylerdir. Aşağıdakiler tek tek kontrol
edildi ve kusur çıkmadı:

- **SoftDeletes** — 37 modelin hepsinde.
- **Yetkilendirme kapsamı** — 26 policy var, 11 model policy'siz. Onların
  hepsi ya bir üst modelin policy'sinden (`ContentFile` → içeriğin kendisi,
  `SubscriberList` → `Subscriber::manageLists`) ya da bir Gate'ten
  (`view-reports`, `view-queue`, `view-analytics`) geçiyor. Yetkisiz uç yok.
- **API katmanı** — Sanctum jeton yetkileri (`abilities`/`ability`), uca özel
  hız sınırları (giriş, kayıt, şifre, doğrulama), hesabı kapatılmış kullanıcı
  ve bakım modu için ara katmanlar, JSON zorlaması (hata yanıtları dahil),
  güvenlik başlıkları, CORS, `Accept-Language` ile yanıt dili. 15 API test
  sınıfı, `openapi.json` kendi kendini denetliyor.
- **N+1** — 25 dışa aktarma listesinde satır başına sorgu yok (bkz. 3).
- **Kod stili ve statik analiz** — Pint sıfır sapma, PHPStan (larastan, seviye 1)
  sıfır hata.
- **Yapı taşı yasakları** — build araçları yok (`NoBuildToolchainTest`),
  `Schedule::command()` yok (`ScheduleUsesCallablesTest`), doğrulama sınırları
  şema ile eşleşiyor (`ValidationLimitsMatchTheSchemaTest`).

---

## Tarayıcıda doğrulanan

Test yeşil olması bir şeyin *çalıştığını* değil, *iddia edilen şeyin
sınandığını* gösterir. Aşağıdakiler ayrıca gerçek tarayıcıda, gerçek oturumla
doğrulandı:

- Üç yeni ekranın dışa aktarma menüsü açılıyor ve üç seçeneği gösteriyor.
- Üç biçim de imzası doğru dosya indiriyor: CSV `EF BB BF` (BOM), XLSX
  `50 4B 03 04` (ZIP), PDF `25 50 44 46`.
- İnen CSV'de Türkçe harfler bozulmuyor, tarihler biçimli, dil kodları büyük
  harf, durumlar çevrilmiş:
  `Tür;Başlık;Dil;Durum;Oluşturulma;Güncelleme` →
  `"Galeri Öğesi";"Çalışma alanı";TR;Yayında;"28.08.2026 21:40";…`
- Kullanıcılar, Raporlar, İçerikler ve Özel Adresler ekranlarının hepsinde üç
  biçim de bağlı.
- 2 MB üstü avatar seçildiğinde `AdminModal` açılıyor, tarayıcı kutusu değil,
  ve girdi temizleniyor.
- Satır içi stilden taşınan biçimler ölçülen değerleriyle aynı (bildirim
  listesi: 380 px / 500 px / auto).
- Çerez rızası kutuları `data-fv-ignore` taşıyor ve bant çalışıyor.

---

## Tur 3 — Boşluk analizinin kalan dört bulgusu

Bu turun sonunda ortaya çıktı ki denetimin bir yarısı eksikti: 31 Ağustos'ta
yapılan **Base Kit Boşluk Analizi** depoda değil, bir Artifact olarak
duruyordu. Belge [`BOSLUK-ANALIZI.md`](BOSLUK-ANALIZI.md) olarak arşive alındı
ve on beş bulgusu bugünkü koda karşı tek tek kontrol edildi: on biri
kapanmıştı, **dördü hâlâ açıktı.**

Dördü de bu turda kapatıldı. Bu bulgular v2 denetiminin kendi eksenine
girmiyordu — o denetim kural/bekçi boşluklarına bakmıştı, bu dördü ise üretim
ve performans katmanına ait.

### S-05 — Content-Security-Policy yok *(Yüksek)*

**Hata.** `SecurityHeaders` beş başlık basıyordu ve doğru olanları seçmişti,
ama CSP yoktu — XSS'e karşı ikinci savunma hattı olan tek başlık. Bu, sıradan
bir eksiklikten fazlasıydı: panelde `custom_head_code` ayarı ham HTML olarak
basılıyor ve mail şablonları zengin metin editörüyle düzenleniyor. Blade'in
kaçışı doğru kullanılmış, ama tek savunma oydu.

**Çözüm.** Nonce tabanlı politika. Her istekte bir kerelik anahtar üretiliyor;
sayfadaki satır içi betikler onu taşıyor, saldırganın enjekte ettiği betik
taşıyamıyor ve çalışmıyor.

- `ContentSecurityPolicy` politikayı kuruyor ve nonce'u istek boyunca taşıyor
  (`scoped` bağ: aynı istekte tek anahtar, uzun ömürlü süreçte her istekte
  yeni).
- Görünüm ağacındaki **39 satır içi betiğe** `nonce="{{ csp_nonce() }}"`
  eklendi; bir tanesinin unutulmadığını bekçi sınıyor.
- Panel ile ön yüz **ayrı politika** alıyor: zengin metin editörü `blob:`
  kaynaklı görsel üretiyor ve kendi iskeletini bir iframe'de açıyor; ziyaretçi
  yüzeyine o izinleri vermek kazanç olmadan yüzeyi genişletirdi.
- `style-src` tarafında `'unsafe-inline'` bilinçli olarak duruyor: Bootstrap'in
  konumlandırıcısı açılır menüleri yerleştirirken elemanın `style` niteliğini
  yazıyor. Betik tarafında ise o anahtar hiç yok — orada olsaydı politika
  anlamsız kalırdı ve bunu ayrı bir test bekliyor.
- İhlal raporları `/csp-ihlali` ucuna düşüyor: hız sınırlı, gövde tavanı olan,
  yalnız tanınan alanları loglayan dar bir uç. Rapor gönderen tarayıcı oturum
  çerezi taşımadığı için kimlik istenemiyor; koruma bu üç kapıdan geliyor.
- `X-XSS-Protection` **kaldırıldı** — güncel hiçbir tarayıcı desteklemiyor ve
  bazı eski sürümlerde filtrenin kendisi XSS'i kolaylaştırıyordu.

**Yol üzerinde bulunan.** İlk denemede tarayıcı konsolu ihlal bildirdi ve
kaynağı Laravel Debugbar çıktı — sayfaya kendi betiğini basıyor ve nonce
taşımıyordu. Debugbar `csp-nonce` adlı bir container bağı arıyor; o bağ
sağlandı, hem Debugbar hem aynı sözleşmeyi kullanan başka paketler çözüldü.
Yalnız geliştirme ortamını ilgilendiren bir çakışmaydı ama "CSP çalışmıyor"
izlenimi bırakırdı.

**Test.** `ContentSecurityPolicyTest` — 18 test.

**Değişen dosyalar:** `app/Services/ContentSecurityPolicy.php`,
`app/Http/Controllers/CspReportController.php` (yeni),
`app/Http/Middleware/SecurityHeaders.php`, `config/security.php` (yeni),
`app/Helpers/helpers.php`, `app/Providers/AppServiceProvider.php`,
`routes/web.php`, 27 görünüm.

---

### S-12 — Analitik cache temizliği tüm cache'i siliyordu

**Hata.** `AnalyticsService::flushCache()` doğrudan `Cache::flush()`
çağırıyordu. Kendi yorumu gerekçesini yazıyordu — sürücü etiket
desteklemeyebilir — ama sonuç, analitik ekranındaki tek bir yenilemenin
ayarları, çevirileri, site haritasını, dil listesini ve bütün ön yüz içerik
önbelleğini birlikte silmesiydi. Varsayılan sürücü veritabanı olduğu için
yeniden ısınmanın bedelini de ilk ziyaretçiler ödüyordu.

İlginç ayrıntı: yorumda *"cache:clear gibi davranmak yerine bilinen prefix'leri
tek tek temizle"* yazıyordu. Niyet yazılmış, uygulanmamıştı.

**Çözüm.** `CachePurger` — önek bazlı temizlik, sürücüye göre:

| Sürücü | Yol | Neden |
|---|---|---|
| Veritabanı | tek `DELETE ... LIKE` | anahtarlar adıyla duruyor |
| Redis | `SCAN` + `DEL` | `KEYS` bütün anahtar uzayını tarar, sunucuyu kilitler |
| Dizi | bellekteki dizinin taranması | test ortamı gerçeğinden ayrılmasın |
| Dosya | yazarken tutulan kayıt | anahtar diskte hash, önek diye bir şey yok |

**Yol üzerinde bulunan.** İlk yazımda LIKE kalıbını kendi kaçış metodumla
kurmuştum ve `LikeSearchIsPortableTest` onu yakaladı — kaçış karakteri ters
bölüydü, yani MySQL'de tam olarak S-11'de bulunan sözdizimi hatasını doğuran
biçim. `LikeSearch::prefix()` + `LikeSearch::clause()` kullanıldı; bekçi kendi
işini yaptı.

---

### S-13 — Cache anahtarları otuz ayrı yerde elle temizleniyordu

**Hata.** Temizlik çağrıları 30'dan fazla yere dağılmıştı ve anahtarlar dizge
sabitiydi (`'sitemap.urls'`, `'admin.pages.stats'`…). Hangi içeriğin hangi
türev önbelleği beslediği kodun içine gömülüydü: yeni bir içerik türü
eklendiğinde `sitemap.urls`'i unutmak, site haritasının bir saat bayat
kalmasına yol açıyordu — hata vermeden, testi kırmadan.

**Çözüm.** `App\Support\CacheKeys` — bütün anahtarlar ve önekler tek yerde.
**52 çağrı, 19 dosyada** sabite bağlandı ve dizge olarak yazılmış anahtar
kalmadığını bir bekçi sınıyor. `contentKeys()` ise "içerik değişti" sinyalini
tek yerde topluyor: yeni bir tür eklendiğinde neyin düşeceği orada
güncelleniyor, çağıran yerler değil.

İkinci bekçi daha ince bir tuzağı kapatıyor: önek taşıyan anahtarlar doğrudan
`Cache::put()` ile yazılırsa dosya sürücüsünde kayda girmiyor ve **hiçbir zaman
temizlenmiyor**. O anahtarların tek doğru yazma yolu
`CachePurger::rememberWithin()`, ve bunu bir test zorluyor.

---

### S-14 — Ön yüzde çıktı cache'i yoktu

**Hata.** Sorgu düzeyinde önbellek iyi kurulmuştu ama anonim bir ziyaretçinin
gördüğü her sayfa yine tam bir çizim döngüsüydü: menü ağacı, alt bilgi
sütunları, dil listesi her istekte yeniden kuruluyordu. Paylaşımlı hostingde en
büyük kazanç burada.

**Çözüm.** `@cachedInclude` direktifi ve `FragmentCache`. `@include` ile aynı
yerde durur, aynı şekilde çağrılır; farkı, parça önbellekte varsa görünümün
**hiç çizilmemesi** — kazanç buradan geliyor.

Üç kapı parçanın saklanmasını engelliyor:

1. **Oturum açmış kullanıcı** — kendi adını taşıyan bir menü sonraki
   ziyaretçiye gösterilemez.
2. **GET olmayan istek** — form gönderiminden sonra çizilen sayfa o isteğe
   özgü.
3. **Kişiye özel iz taşıyan çıktı** — CSRF anahtarı ya da CSP nonce'u içeren
   bir parça saklanırsa iki hata birden doğar: başkasının anahtarını taşıyan
   form reddedilir, bayat nonce betiği çalıştırılamaz hâle getirir.

**Yol üzerinde bulunan.** İlk hedef alt bilgiydi — her sayfada aynı, menü
ağacı geziyor, iyi bir aday. Üçüncü kapı onu reddetti: alt bilgi bülten formunu
içeriyor, yani CSRF anahtarı taşıyor. Koruma çalıştı ve karar değişti: **gezinti
önbelleğe alınıyor** (10 KB, misafirde temiz), alt bilgi bilinçli olarak
alınmıyor. Gerekçe hem görünümde hem testte yazılı — biri ileride alt bilgiyi
önbelleğe alırsa bekçi kırılıyor.

Menü ya da ayar değişince çizilmiş parçalar düşüyor; ziyaretçi bir saat boyunca
eski bağlantıları görmüyor.

**Test.** `CacheHygieneTest` — 17 test (S-12, S-13 ve S-14 birlikte).

**Değişen dosyalar:** `app/Support/CacheKeys.php`, `app/Services/CachePurger.php`,
`app/Services/FragmentCache.php` (yeni), `app/Services/AnalyticsService.php`,
`app/Services/MenuService.php`, `app/Models/Setting.php`, `config/cache.php`,
`resources/views/layouts/app.blade.php`, 19 servis/observer.

---

## Açık kalan iki madde

İkisi de bu denetimden önce de bilerek açıktı; durumları değişmedi.

- **Panelden push bildirim gönderme ekranı.** Sunucu tarafı hazır (jeton kaydı,
  sağlayıcıdan bağımsız gönderim servisi, ölü jetonun düşmesi). Admin temada bu
  ekranın tasarımı yok ve tasarımda olmayan bir ekranı uydurmak proje kuralına
  aykırı. Tasarım geldiğinde ya da onay verildiğinde yapılacak.
- **`session.serialization = json`.** Çevirmek o anda açık olan bütün oturumları
  düşürüyor; çalışan bir kurulumda bu bakım penceresi gerektiren bir karar, kod
  değil zamanlama meselesi.

---

## Ders

Bu denetimin tek cümlelik özeti şu: **kural yazmak yetmiyor, kuralın bekçisi
olması gerekiyor — ve bekçinin kapsamı elle yazılmış bir listeye bağlıysa o
bekçi zamanla kör oluyor.**

Bulunan on altı kusurun on ikisi bu desende. Hiçbiri kodun yanlış yazılmasından
doğmadı; hepsi projenin bir yerinin büyürken bekçisinin yerinde kalmasından
doğdu. Bu turda kurulan bekçilerin ortak özelliği de bu: hiçbiri elle yazılmış
liste kullanmıyor. Rota tablosundan, görünüm ağacından, kayıt defterinden,
dil dizininden besleniyorlar. Bir sonraki ekran eklendiğinde kapsama kendiliğinden
giriyor; girmemesi gereken şey varsa gerekçesi yazılı bir istisna listesine
konuyor ve o listenin bayatlamadığı ayrıca sınanıyor.
