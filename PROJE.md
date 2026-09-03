# Bu Proje Nedir?

> **Buraya yeni geldiyseniz —insan ya da yapay zekâ— önce bu dosyayı okuyun.**
> Beş dakika sürer ve sizi on iki belgenin hangisine gideceğinize yönlendirir.
> Kod yazmadan önce ayrıca [`CLAUDE.md`](CLAUDE.md) zorunludur: orada tartışmaya
> kapalı kurallar var.

---

## Tek cümle

Kurumsal web sitesi, yönetim paneli ve mobil uygulama API'sini birlikte taşıyan,
**paylaşımlı hosting'de çalışacak şekilde kurulmuş** yeniden kullanılabilir bir
Laravel altyapısı.

Yeni bir proje bu depoyu klonlayıp üstüne kendi modüllerini ekleyerek başlar.
Blog, galeri, menü, ayarlar, mail şablonları, yedekleme, analitik, yetkilendirme,
çok dillilik ve hata izleme hazır gelir.

**Stack:** PHP 8.4 · Laravel 13 · Blade · MySQL 8 · Bootstrap 5.3.8 · Vanilla JS

---

## Ne DEĞİLDİR — en önemli bölüm

Aşağıdakiler eksiklik değil, **bilinçli karar**. Her biri bir kısıttan doğdu ve
her birinin ihlali bir testle yakalanıyor. "Yardımcı olmak" için bunlardan birini
eklemek, projeyi çalışmaz hâle getirir.

| Bu proje… | Çünkü | Bunun yerine |
|---|---|---|
| **Build adımı yoktur.** Vite, npm, Node.js, Webpack yok | Hedef sunucuda Node yok; deploy `git pull` + `composer install`'dan ibaret olmalı | Vendor dosyaları `public/assets/vendor/` altında hazır durur; `versioned_asset()` dosyanın `mtime`'ıyla önbellek kırar |
| **SPA değildir.** React, Vue, Angular, Livewire, Inertia yok | Sunucuda render edilen Blade; JS yalnız etkileşim için | Vanilla JS, `fetch` |
| **jQuery ile yazılmaz** | jQuery **yalnız** Validation Engine 3.1.0 için yüklü | Kendi kodunuz vanilla JS |
| **`queue:work` çalıştırmaz** | Paylaşımlı hosting'de `pcntl` yok, uzun ömürlü süreç açılamaz | Kuyruk cron'la sürülür: `Queue::pop()` + `fire()` |
| **`Schedule::command()` kullanmaz** | Alt süreç açılamıyor; ihlal edilirse görev **hata vermeden hiç çalışmaz** | `Schedule::call()` + `Artisan::call()` |
| **`storage:link` gerektirmez** | Sembolik bağ her hosting'de kurulamıyor | Yüklemeler doğrudan `public/uploads/` altına iner, `UploadService` üzerinden |
| **`Storage::disk('public')` kullanmaz** | Yukarıdakiyle aynı sebep | `UploadService` (WebP dönüşümü + 4 boy varyant) |
| **`alert()` / `confirm()` / `prompt()` kullanmaz** | Tarayıcı kutuları tasarımın dışında ve engellenebiliyor | `AdminModal` (panel), özel modal (ön yüz) |
| **HTML5 form doğrulaması kullanmaz** | Mesajlar dile ve tasarıma uymuyor | `data-validation-engine="validate[...]"` |
| **Inline `style=""` yazmaz** | Tema değişkenleri ve CSP | CSS sınıfı |
| **Yönetim paneli çok dilli değildir** | Bilinçli kapsam kararı: panel tek dilli (Türkçe), `SetAdminLocale` sabitliyor | Çok dillilik **ön yüz** kapsamındadır |

> Bu listeye bir şey eklemek isterseniz önce ilgili testi bulun; kural oradan
> okunur. Sebepleri: [`docs/SHARED-HOSTING.md`](docs/SHARED-HOSTING.md)

---

## Mimari — bir istek nasıl akar

```
Route → Controller (ince)  → FormRequest  (doğrulama)
                           → Policy       (yetkilendirme)
                           → Service      (iş mantığı)
                           → Model        (+ Observer)
```

Değişmeyen dört kural:

- Controller'da iş mantığı yok — her şey `app/Services/` altında
- Her modelde `SoftDeletes` ve `$fillable` (`$guarded = []` yasak)
- Her PHP dosyasında `declare(strict_types=1)`
- Yetkilendirme **veritabanı tabanlı**: kullanıcı → rol → izin. Kodda rol adı
  karşılaştırması yapılmaz, `PermissionKey` enum'u kullanılır

Ön yüz ve panel varlıkları **tamamen ayrıdır** — `public/css` + `public/js`
ön yüz, `public/assets/admin/` panel. Aynı dosya iki tarafta kullanılmaz.

---

## Bu repo kendini korur

Kuralları ezberlemenize gerek yok. **134 test dosyası, 2144 test** var ve önemli
bir kısmı kod yazmayı değil, kuralı denetliyor. Bir kuralı çiğnerseniz test
kırmızıya döner ve neyi çiğnediğinizi adıyla söyler:

| Bekçi | Ne yakalar |
|---|---|
| `NoBuildToolchainTest` | `package.json` / `vite.config.js` geri girerse |
| `ScheduleUsesCallablesTest` | `Schedule::command()` kullanılırsa |
| `BrowserDialogsAreForbiddenTest` | `alert()` / `confirm()` / `prompt()` |
| `InlineStylesAreForbiddenTest` | `style="..."` |
| `InlineHandlersAreForbiddenTest` | `onclick=` gibi nitelik işleyicileri |
| `FormFieldsCarryRulesTest` | Doğrulama kuralı taşımayan form alanı |
| `ListScreensOfferExportTest` | Dışa aktarma sunmayan liste ekranı |
| `AdminAuthorizationTest` | Yetki matrisine girmemiş yeni panel ekranı |
| `AdminHelpTest` | Kılavuzu yazılmamış yeni modül |
| `LikeSearchIsPortableTest` | Elle kurulmuş `LIKE` (MySQL'de kırılır) |
| `DocsCiteRealTestsTest` | Belgelerin var olmayan bir teste gönderme yapması |

**Pratik sonuç:** emin değilseniz yazın ve `php artisan test` koşun. Bekçiler
size kuralı söyler.

Ayrıca `./vendor/bin/pint --test` (biçim) ve `./vendor/bin/phpstan analyse`
(statik analiz) CI'da koşuyor; ikisi de temiz olmalı.

---

## Belge haritası — sorunuza göre

| Sorunuz | Dosya |
|---|---|
| Kod yazacağım, kurallar ne? | [`CLAUDE.md`](CLAUDE.md) — **zorunlu** |
| Sunucuya nasıl kurarım? | [`SETUP.md`](SETUP.md) |
| Bu kit'ten yeni proje türeteceğim | [`docs/YENI-PROJE.md`](docs/YENI-PROJE.md) |
| Yayındaki kurulumu güncelleyeceğim | [`docs/CANLIYA-ALMA.md`](docs/CANLIYA-ALMA.md) |
| Mobil uygulama yazacağım, API? | [`docs/API.md`](docs/API.md) · [`docs/openapi.json`](docs/openapi.json) |
| Neden cron? Neden `queue:work` yok? | [`docs/SHARED-HOSTING.md`](docs/SHARED-HOSTING.md) |
| Hangi özellik nasıl çalışıyor? | [`README.md`](README.md) — özellik kataloğu, 27 bölüm |
| Yeni panel sayfası yapacağım | `resources/views/admin-theme/README.md` |

### Arşiv — buraya *ihtiyaç duymadıkça* girmeyin

Aşağıdakiler geçmiş kaydıdır, rehber değildir. Toplam **8.217 satır**; bir sorunun
"ne zaman, neden böyle yapıldı" tarafını araştırmıyorsanız açmayın.

- `docs/PROJE-KAYDI.md` (4.685 satır) — çalışma günlüğü
- `docs/PROJE-DURUMU.md` (2.077) · `docs/PROJE-DURUMU-V2.md` (647) — denetim kayıtları
- `docs/BOSLUK-ANALIZI.md` (468) — mimari denetim bulguları
- `docs/YOL-HARITASI.md` (340) — fazlar ve kabul ölçütleri

---

## İlk 15 dakika

1. Bu dosyayı bitirin (bitti).
2. [`CLAUDE.md`](CLAUDE.md) — 147 satır, kuralların tamamı.
3. `php artisan test` koşun. Yeşil görmek, ortamınızın çalıştığının kanıtı.
4. `app/Services/` dizinine bakın — iş mantığının tamamı orada, dosya adları
   ne yaptıklarını söylüyor.
5. `routes/admin.php` ve `routes/web.php` — projenin yüzeyi.

Bir şeyi değiştirmeden önce sorun: **bunun bir bekçisi var mı?** Genelde vardır.
