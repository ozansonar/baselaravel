# API (v1) — Mobil ve harici istemciler

Proje hem Blade ile üretilen web arayüzünü hem de **Laravel Sanctum** jetonuyla
konuşan istemcileri (Flutter uygulaması, harici bir SPA) aynı iş mantığından
besler. Panelden değiştirilen bir menü, bir ayar ya da bir yazı iki tarafta da
aynı anda değişir — API ayrı bir kopya tutmaz, aynı Service katmanını çağırır.

Taban adres: `https://site-adresi/api/v1`

---

## İçindekiler

- [Yanıt biçimi](#yanıt-biçimi)
- [Dil](#dil)
- [Kimlik doğrulama](#kimlik-doğrulama)
- [Şifre sıfırlama](#şifre-sıfırlama)
- [E-posta doğrulama](#e-posta-doğrulama)
- [Cihazlar](#cihazlar)
- [Hesap](#hesap)
- [Uçlar](#uçlar)
- [Hız sınırları](#hız-sınırları)
- [CORS](#cors)
- [Yapılandırma](#yapılandırma)
- [Sürümleme](#sürümleme)

---

## Yanıt biçimi

Her yanıt aynı zarfı taşır. Zarf `App\Http\Responses\ApiResponse` içinde
kurulur; hatalar `App\Exceptions\ApiExceptionRenderer` tarafından aynı biçime
çevrilir.

**Başarı**

```json
{
  "success": true,
  "message": "İşlem başarılı.",
  "data": { }
}
```

**Hata**

```json
{
  "success": false,
  "message": "E-posta alanı zorunludur.",
  "errors": {
    "email": ["E-posta alanı zorunludur."]
  }
}
```

`data` başarıda, `errors` hatada **her zaman** bulunur (içi boş olsa bile).
Boş `errors` JSON'da `{}` olarak iner, `[]` olarak değil — istemci aynı alanı
iki ayrı tipte görmesin diye.

**Sayfalı listeler** zarfa iki anahtar daha ekler:

```json
{
  "success": true,
  "message": "İşlem başarılı.",
  "data": [ ],
  "meta": {
    "current_page": 1,
    "last_page": 4,
    "per_page": 15,
    "total": 52,
    "from": 1,
    "to": 15,
    "has_more": true
  },
  "links": {
    "first": "...?page=1",
    "last": "...?page=4",
    "prev": null,
    "next": "...?page=2"
  }
}
```

Sayfa boyutu `?per_page` ile değiştirilir; tavan `config/api.php` içinde
(varsayılan 100).

### Durum kodları

| Kod | Anlamı |
|---|---|
| 200 | Başarılı |
| 201 | Kayıt oluşturuldu (kayıt olma, iletişim formu) |
| 401 | Jeton yok, geçersiz ya da süresi dolmuş |
| 403 | Hesap pasif, kayıt kapalı, yetki yok |
| 404 | Kayıt / uç bulunamadı |
| 422 | Doğrulama hatası — alan bazlı `errors` |
| 429 | Hız sınırı — `Retry-After` başlığı ne kadar bekleneceğini söyler |
| 503 | Bakım modu |
| 500 | Beklenmedik hata (mesaj yalnız `APP_DEBUG=true` iken açık) |

`Accept` başlığı gönderilmese bile yanıt JSON'dur
(`App\Http\Middleware\ForceJsonResponse`).

---

## Dil

API'de adres dil taşımaz (ön yüzdeki `/tr/blog` gibi) ve oturum yoktur. Dil
istekten çözülür — `App\Http\Middleware\SetApiLocale`:

1. `?lang=en`
2. `X-Locale: en`
3. `Accept-Language: en-GB,en;q=0.9,tr;q=0.8` — bölgesel etiketler ve q
   değerleri dikkate alınır (`de-DE` → `de`)
4. sitenin varsayılan dili

Sitede olmayan bir dil **hata değildir**: varsayılan dile düşülür. Mobil
uygulama cihazın dilini gönderir ve o dil sitede yoksa kullanıcı 404 değil,
içerik görmelidir.

Seçilen dil yanıtta `Content-Language` başlığıyla bildirilir. Hata ve doğrulama
metinleri de o dilde döner.

Yayındaki dillerin listesi: `GET /api/v1/languages`.

---

## Kimlik doğrulama

Sanctum'un kişisel erişim jetonu kullanılır. Jeton `Authorization` başlığında
taşınır:

```
Authorization: Bearer 4|xxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

Jeton **yalnızca üretildiği anda bir kez** düz metin olarak döner; veritabanında
hash'li tutulur. İstemci onu güvenli depoya yazmalıdır (iOS Keychain, Android
Keystore). Varsayılan ömrü 30 gün (`SANCTUM_TOKEN_EXPIRATION`), `expires_at`
yanıtta bildirilir.

`device_name` gönderilirse jetonun etiketi olur. **Aynı cihaz adıyla yeniden
giriş, o cihazın eski jetonunu geçersiz kılar** — aksi hâlde her açılışta bir
satır daha birikir ve kullanıcı hangi oturumu iptal ettiğini bilemez.

Kendi web ön yüzümüz (config/sanctum.php'deki `stateful` listesindeki alan
adları) jeton yerine oturum çereziyle de gelebilir. Mobil uygulama o listeye
girmez.

### `POST /auth/register`

```json
{
  "first_name": "Ozan",
  "last_name": "Sonar",
  "email": "ozan@ornek.com",
  "phone": "0505 000 00 00",
  "password": "Gizli*12345",
  "password_confirmation": "Gizli*12345",
  "device_name": "iPhone 15"
}
```

201 döner; `data`: `user`, `token`, `token_type`, `expires_at`.

Kayıt panelden kapatılmışsa (`registration_enabled`) **403** döner. E-posta
benzersizliği yalnız yaşayan satırlar arasında bakılır: soft delete ile silinmiş
bir hesabın adresi yeniden kullanılabilir.

Kayıt, ön yüzdekiyle aynı akışı çalıştırır — varsayılan rol atanır, hoş geldin
maili ve e-posta doğrulama bağlantısı gönderilir.

### `POST /auth/login`

```json
{ "email": "ozan@ornek.com", "password": "Gizli*12345", "device_name": "iPhone 15" }
```

Yanıt gövdesi kayıtla aynıdır. Kimlik bilgileri tutmuyorsa **401** (hangi alanın
yanlış olduğu bilerek söylenmez), hesap pasifse **403**.

### `POST /auth/logout` *(jeton gerekli)*

Yalnız isteği yapan cihazın jetonunu siler; kullanıcının öteki cihazları ayakta
kalır.

### `GET /auth/me` *(jeton gerekli)*

Giriş yapmış kullanıcı, rolleriyle birlikte. Parola ve `remember_token` hiçbir
koşulda dönmez.

Hesap panelden pasife alınırsa jetonları **o anda** silinir (`SessionRevoker`,
web oturumlarıyla birlikte) ve sonraki istek 401 döner. Bayrak gözlemciyi
uyandırmadan değişmişse (toplu işlem, elle sorgu) `EnsureApiUserIsActive` ikinci
savunma hattı olarak 403 döner ve jetonu siler.

---

## Şifre sıfırlama

Web'de sıfırlama bağlantısı maille gelir ve şifre tarayıcıda değiştirilir.
Mobilde bu akış kopuk olurdu — kullanıcı uygulamadan çıkıp tarayıcıda işini
bitirip geri dönmek zorunda kalırdı. API'de bunun yerine **altı haneli kod**
gider.

### `POST /auth/password/forgot`

```json
{ "email": "ozan@ornek.com" }
```

```json
{
  "success": true,
  "message": "Adres kayıtlıysa şifre sıfırlama kodu gönderildi.",
  "data": { "expires_in_minutes": 60 }
}
```

**Adres kayıtlı olsun ya da olmasın yanıt aynıdır.** Ayırt edilebilseydi bu uç,
hangi adreslerin sistemde olduğunu öğrenmenin en kolay yolu olurdu. Pasif
hesaplara da kod gitmez.

### `POST /auth/password/reset`

```json
{
  "email": "ozan@ornek.com",
  "code": "482915",
  "password": "Yeni*12345",
  "password_confirmation": "Yeni*12345"
}
```

Kod yanlış ya da süresi dolmuşsa **422** döner; hangisi olduğu söylenmez.
Başarıda jeton dönmez — kullanıcı yeni şifresiyle giriş yapar.

**Sıfırlama, o kullanıcının bütün oturumlarını ve bütün cihazlardaki jetonlarını
düşürür.** Sıfırlamanın varlık sebebi çoğu zaman hesabın elden çıkmış olmasıdır;
eski erişim ayakta kalsaydı sıfırlama, erişimi geri almak yerine yalnızca bir
parola değişikliği olurdu.

> **Altı hane neden yeterli.** Tek başına değil. Bir milyon olasılık sınırsız
> denemeye açık bırakılsaydı dakikalar içinde tükenirdi. Üç şey bir arada
> tutuyor: kod 60 dakikada ölüyor, uç e-posta+IP başına dakikada 5 isteğe sınırlı
> (`api-password` kovası — kod isteme ve kod deneme **aynı** kovayı paylaşır, yani
> yeni kod isteyerek deneme kotası tazelenemez), ve başarılı sıfırlama satırı
> hemen siliyor. Hız sınırı bu tasarımın süsü değil taşıyıcı direğidir:
> `API_RATE_LIMIT_PASSWORD` yükseltilirse kod zayıflar.

Kod, web'in bağlantı jetonuyla **aynı tabloda** (`password_reset_tokens`) ve
hash'lenmiş olarak durur. Yani bir kullanıcı için aynı anda tek bir sıfırlama
isteği yaşar: kod istenirse web'den alınmış bağlantı, bağlantı istenirse kod
geçersiz olur.

---

## E-posta doğrulama

Kayıt sırasında doğrulama bağlantısı gönderilir. Bağlantı tarayıcıda açılır ve
doğrulamayı orada tamamlar — mobil tarafta deep link kurulumu gerektirmeyen tek
adım budur.

| Yöntem | Adres | Açıklama |
|---|---|---|
| POST | `/auth/email/resend` | Bağlantıyı yeniden gönderir *(jeton gerekli)* |

`GET /auth/me` yanıtındaki `email_verified` alanı uygulamanın "e-postanı doğrula"
ekranını çizmesi için vardır; bu yüzden `/auth/me` doğrulama şartının **dışında**
tutulur. Hesap uçları ise doğrulama ister — ön yüzdeki `/hesabim` da öyle.

Doğrulanmamış kullanıcı hesap ucuna giderse **403** ve şu gövde döner:

```json
{ "success": false, "message": "...", "errors": { "code": ["email_unverified"] } }
```

`errors.code` bilerek makine tarafından okunabilir: istemci metni ayrıştırmadan
doğrulama ekranına yönlendirebilsin.

---

## Cihazlar

*(jeton gerekli)*

Jeton, oturum çerezinden farklı olarak kendiliğinden sona ermiyor ve sahibi
hangi cihazlarda açık olduğunu göremiyordu: telefonunu kaybeden kişinin elinde
tek seçenek şifresini değiştirmekti.

| Yöntem | Adres | Açıklama |
|---|---|---|
| GET | `/auth/devices` | Açık oturumlar, en son kullanılan başta |
| DELETE | `/auth/devices/{id}` | Tek bir oturumu kapatır |
| DELETE | `/auth/devices` | **Bu cihaz hariç** hepsini kapatır |

```json
{
  "id": 12,
  "name": "iPhone 15",
  "current": true,
  "last_used_at": "2026-08-31T16:04:11+03:00",
  "created_at": "2026-08-20T09:12:00+03:00",
  "expires_at": "2026-09-19T09:12:00+03:00"
}
```

Jetonun kendisi hiçbir koşulda listelenmez — Sanctum onu hash'li tutar ve düz
metni yalnız üretildiği anda görülür. `current` alanı istemcinin "bu cihaz"
etiketini basması ve kullanıcının yanlışlıkla kendi oturumunu kapatmaması için
var.

`DELETE /auth/devices` mevcut oturumu **korur**: düğmeye basan kişi kendi
uygulamasından atılmayı beklemiyor. Kendi oturumunu kapatmak isteyen `logout`
kullanır.

Başkasının oturum kimliği yazılırsa **404** döner, 403 değil. Ayrımı söylemek,
kimlikleri tek tek deneyerek başka hesapların oturumlarını haritalamaya yarardı.

Süresi dolmuş jetonlar listelenmez: Sanctum onları zaten kabul etmiyor, listede
durmaları kapatılabilecek bir oturum varmış gibi gösterirdi.

> Bu uçlar **doğrulanmış e-posta istemez.** Hesabına şüpheli bir erişim olduğunu
> düşünen kişi, doğrulama adımını tamamlayamamış olsa bile oturumları
> kapatabilmeli.

---

## Hesap

*(jeton + açık hesap + doğrulanmış e-posta gerekli)*

### `PUT /account/profile`

```json
{
  "first_name": "Ozan",
  "last_name": "Sonar",
  "email": "ozan@ornek.com",
  "phone": "0505 000 00 00",
  "current_password": "Eski*12345",
  "password": "Yeni*12345",
  "password_confirmation": "Yeni*12345",
  "remove_avatar": false
}
```

Doğrulama kuralları ön yüzle **aynı sınıftan** gelir
(`App\Http\Requests\Account\ProfileUpdateRequest`). Bunun bir sonucu var:
güncelleme tamdır, parçalı değil — ad, soyad ve e-posta her istekte gönderilir.

Şifre değiştirmek `current_password` ister: ele geçirilmiş bir jeton, gerçek
sahibi hesabından kilitleyememeli.

> **E-posta adresi değişirse doğrulama sıfırlanır.** Damga adrese aittir, hesaba
> değil. Yanıt bunu iki yerden söyler: `data.email_verified` `false` döner ve
> `message` sebebi anlatır. Yeni adrese kendiliğinden taze bir doğrulama
> bağlantısı gider.
>
> Pratikte bunun anlamı şu: **adresi değiştiren istek başarılı olur, bir
> sonraki istek 403 verir** (`errors.code = "email_unverified"`). İstemci bu
> yanıttan sonra kullanıcıyı doğrulama ekranına almalı; `POST /auth/email/resend`
> oradan çağrılır.
>
> Ayrıca **eski adrese bir güvenlik uyarısı gider**. Hesabı ele geçiren kişinin
> ilk yaptığı şey çoğu zaman adresi değiştirmektir; yeni adrese giden doğrulama
> maili o senaryoda saldırganın kutusuna düşer, yani kimseyi uyarmaz. Eski adres
> sahibin durumu öğrenebileceği tek yerdir.

**Avatar aynı istekte** gider. PHP çok parçalı gövdeyi yalnız POST'ta
ayrıştırdığı için istemci dosyayla birlikte `POST` + `_method=PUT` kullanmalıdır:

```
POST /api/v1/account/profile
Content-Type: multipart/form-data

_method=PUT&first_name=Ozan&last_name=Sonar&email=...&avatar=@ben.jpg
```

---

## Uçlar

Jeton gerektirmeyen uçlar bakım modunda **503** döner; kimlik uçları bakım
modunda da açık kalır (ön yüzde `/giris` de öyle).

### Site geneli

| Yöntem | Adres | Açıklama |
|---|---|---|
| GET | `/languages` | Yayındaki diller; `meta.current` ve `meta.default` |
| GET | `/settings` | Dışarı açılan ayarlar, gruplarına göre |
| GET | `/settings?group=contact` | Tek grup |
| GET | `/translations` | Arayüz metinleri (düz anahtarlar: `nav.home`) |
| GET | `/translations?group=site` | Tek grup |
| GET | `/menus` | Bütün konumların menüleri, ağaç hâlinde |
| GET | `/menus/{location}` | Tek konum (`header`, `footer`, `custom`) |
| GET | `/pages` | Yayındaki sayfalar (menü için; içerik taşımaz) |
| GET | `/pages/{slug}` | Sayfa içeriği — HTML |
| GET | `/sliders` | Ana sayfa görsel şeridi |
| GET | `/faqs` | Sıkça sorulan sorular |
| GET | `/home` | Açılış ekranının tamamı tek istekte |

**`/home` üç bölümü bir arada verir**: `sliders`, `posts` (son yazılar) ve
`gallery` (fotoğraf şeridi). Parçalar ayrı ayrı da yayında; bu uç uygulama
açılışındaki üç gidiş dönüşü bire indirmek için var — mobil bağlantıda ekranın
gecikmesinin büyük kısmı o. Bölüm başına kaç kayıt döneceği `config/api.php` →
`home` içinde ve ön yüzdeki ana sayfayla aynı sayılarla başlar.

**Slider buton adresi çözülmüş ve dile duyarlı gelir.** Panelde `/iletisim`
yazılı olsa bile İngilizce isteyen istemci İngilizce adresi alır; ham yol
verilseydi uygulama yanlış dildeki sayfaya düşerdi. Butonu olmayan slider'da
alan `null` olur.

**`/settings` her ayarı yayınlamaz.** settings tablosu SMTP parolasını, reCAPTCHA
gizli anahtarını ve Telegram jetonunu da tutar. Yayınlanacak gruplar ve elenen
anahtarlar `config/api.php` → `public_settings` içinde tanımlıdır; tipi
`password` olan ya da adında `secret`, `token`, `password`, `api_key`, `private`
geçen hiçbir satır — grubu ne olursa olsun — çıkmaz. Görsel tipindeki ayarlar
mutlak adres olarak döner.

**Sayfalar mağaza onayı için kritiktir.** Gizlilik politikası, KVKK ve kullanım
koşulları panelden yayınlanıp buradan okunur; metinler uygulamaya gömülseydi her
düzeltme bir mağaza güncellemesi beklerdi. `content` zengin metin editöründen
gelir, yani HTML (`content_format: "html"`) — istemci onu bir HTML
görüntüleyicide basmalıdır; düz metne çevirmek yasal metinlerde biçim değil
içerik kaybıdır.

**Menü bağlantıları çözülmüş gelir.** Kayıt bir rota adı da tutabilir
(`blog.index`), site içi bir yol da, harici bir adres de; `url` alanı bunların
hepsini çözülmüş hâlde verir. Panelden bir sayfanın adresi değiştiğinde mobil
uygulamanın güncellenmesi gerekmez.

### Blog

| Yöntem | Adres | Açıklama |
|---|---|---|
| GET | `/blog/posts` | Yayındaki yazılar, sayfalı |
| GET | `/blog/posts?category={slug}&per_page=20` | Kategoriye göre |
| GET | `/blog/posts/{slug}` | Yazı detayı — gövde, SEO alanları, ekler, yorum sayısı |
| GET | `/blog/posts/{slug}/comments` | Onaylı yorumlar, yanıtlarıyla ağaç olarak |
| POST | `/blog/comments` | Yorum gönderir — onay bekler |
| GET | `/blog/categories` | Etkin kategoriler, yazı sayılarıyla |

Liste yanıtı **gövde taşımaz** (`body`): yirmi yazılık bir sayfa aksi hâlde
yirmi tam metin demek olurdu. Kategori ve yazar ilişkileri baştan yüklenir
(N+1 yok). Detay ucu okunma sayacını artırır — ön yüzdeki davranışla aynı.

Olmayan bir kategori slug'ı boş liste değil **404** döner: istemci yanlış
yazdığını "bu kategoride yazı yok" sanmamalı.

**Yorumlar detaya gömülü değil.** Detay yanıtı yalnız `comment_count` taşır
(ön yüzdeki sayı gibi üst düzey yorumları sayar, yanıtları değil — aynı yazı
web'de ve uygulamada farklı sayı göstermemeli); yorumların kendisi ayrı uçtan
istenir. Gömülü olsaydı kırk yorumlu bir yazının detayı, yorumları hiç açmayan
bir ekran için bile kırk yorum taşırdı.

Gönderilen yorum **onay bekleyerek** kaydedilir ve listede görünmez; yanıt bunu
söyler. Yorumda `email` ve `ip_address` hiçbir koşulda dışarı çıkmaz — form
yorumcuya "e-posta adresiniz yayınlanmayacaktır" diyor ve bu söz API'de de
tutulur.

> **reCAPTCHA burada da yok** ve yorum alanları spam'in birinci hedefi. İki fren
> var: hız sınırı (IP başına dakikada 3) ve moderasyon — hiçbir gönderim
> doğrudan yayına girmiyor, spam yayına değil kuyruğa düşüyor.

### Galeri

| Yöntem | Adres | Açıklama |
|---|---|---|
| GET | `/gallery` | Etkin öğeler, sayfalı |
| GET | `/gallery?category={slug}&type=photo` | Kategori ve tür süzgeci |
| GET | `/gallery/categories` | Etkin kategoriler |

`type` yalnız `photo` veya `video` olabilir; başka bir değer **422** döner.
Kategori süzgeci slug ile çalışır (kimlikle değil): kategorinin her dilde ayrı
bir satırı vardır ve kimliğe göre süzülseydi o dile çevrilmemiş olduğu için
varsayılan dilden düşen öğeler süzgecin dışında kalırdı.

### Bülten aboneliği

`POST /newsletter/subscribe`

```json
{ "email": "abone@ornek.com", "first_name": "Ozan", "last_name": "Sonar" }
```

Ad ve soyad isteğe bağlı. Abone varsayılan işaretli listeye düşer — ön yüzle
aynı kural. Aynı adresle yeniden abone olmak yeni satır açmaz, mevcut kaydı
canlandırır.

Abonelikten çıkma bilerek yok: çıkış bağlantısı her kampanya mailinin altında,
imzalı ve giriş gerektirmiyor. Uygulamaya taşımak çıkışı zorlaştırmaktan başka
bir işe yaramazdı.

### İletişim formu

`POST /contact`

```json
{
  "name": "Ozan Sonar",
  "email": "ozan@ornek.com",
  "phone": "0505 000 00 00",
  "subject": "Teklif talebi",
  "message": "Merhaba, fiyat listenizi rica ederim."
}
```

201 döner. Kayıt ön yüzdekiyle aynı servisten geçer: aynı tabloya yazılır,
yönetici bildirimi aynı yerden çıkar, panel iki kaynağı ayırt etmek zorunda
kalmaz. Yanıt yalnız gönderenin kendi yazdıklarını geri verir — `ip_address`,
`is_read`, `reply_text` gibi yönetim alanları dışarı çıkmaz (IP yine de
kaydedilir).

> **reCAPTCHA:** ön yüz formunda robot doğrulaması vardır, API'de yoktur —
> tarayıcıya bağlı bir denetimdir ve mobil istemcide karşılığı yoktur. API
> tarafında kötüye kullanımı hız sınırı tutar (IP başına dakikada 3). Daha sıkı
> bir koruma gerekiyorsa `API_RATE_LIMIT_CONTACT` düşürülür.

### Görseller

Görsel alanları beş boyutu birden verir; istemci ekranına göre seçer:

```json
"image": {
  "original": "https://site/uploads/blog/ornek.webp",
  "thumb":    "https://site/uploads/blog/ornek-thumb.webp",
  "sm":       "https://site/uploads/blog/ornek-sm.webp",
  "md":       "https://site/uploads/blog/ornek-md.webp",
  "lg":       "https://site/uploads/blog/ornek-lg.webp"
}
```

Görsel yoksa alan `null` olur — yer tutucu görselin nasıl görüneceği istemcinin
tasarım kararıdır, API'nin değil. Adresler mutlaktır (`APP_URL` ile
tamamlanır).

---

## Hız sınırları

| Kova | Varsayılan | Sayım anahtarı |
|---|---|---|
| `api` | 60/dk | Giriş yapmışsa kullanıcı, değilse IP |
| `api-login` | 5/dk | e-posta + IP |
| `api-register` | 3/dk | IP |
| `api-contact` | 3/dk | IP |
| `api-password` | 5/dk | e-posta + IP — kod isteme ve kod deneme ortak |
| `api-verification` | 3/dk | kullanıcı |
| `api-comment` | 3/dk | IP — ön yüzdekinden (5/dk) bilerek sıkı |
| `api-newsletter` | 5/dk | IP |

Değerler `.env` üzerinden değiştirilir (`API_RATE_LIMIT`,
`API_RATE_LIMIT_LOGIN`, `API_RATE_LIMIT_REGISTER`, `API_RATE_LIMIT_CONTACT`,
`API_RATE_LIMIT_PASSWORD`, `API_RATE_LIMIT_VERIFICATION`, `API_RATE_LIMIT_COMMENT`,
`API_RATE_LIMIT_NEWSLETTER`).

Sınıra takılan istek **429** ve `Retry-After` başlığıyla döner. Giriş kovasının
e-posta+IP ile sayılması, tek IP'den kırk hesabı denemeyi de kırk IP'den tek
hesabı denemeyi de aynı sınıra sokar.

> Ters vekil arkasında `TRUSTED_PROXIES` doldurulmalıdır; yoksa bütün
> ziyaretçiler tek IP görünür ve hepsi aynı kovaya düşer.

---

## CORS

`config/cors.php`. Mobil uygulama CORS'a tabi değildir (`Origin` göndermez); bu
ayarlar harici web ön yüzleri içindir.

```dotenv
CORS_ALLOWED_ORIGINS=https://uygulama.ornek.com,https://panel.ornek.com
CORS_SUPPORTS_CREDENTIALS=false
CORS_MAX_AGE=86400
```

Varsayılan `*`: jeton taşıyan, çerez taşımayan bir API'de her kaynağa açık olmak
bir açık değildir. Ama `CORS_SUPPORTS_CREDENTIALS=true` yapılırsa (oturum
çerezli SPA kurulumu) tarayıcı `*` ile çerez göndermeyi reddeder — o durumda
`CORS_ALLOWED_ORIGINS` mutlaka doldurulur. Doldurulmazsa yapılandırma sessizce
açık kalmak yerine kendi adresimize kilitlenir.

---

## Yapılandırma

`.env`:

```dotenv
# Oturum çerezini kabul eden alan adları — yalnız KENDİ ön yüzünüz.
SANCTUM_STATEFUL_DOMAINS=

# Jeton ömrü (dakika). 43200 = 30 gün.
SANCTUM_TOKEN_EXPIRATION=43200
SANCTUM_TOKEN_PREFIX=

CORS_ALLOWED_ORIGINS=*
CORS_SUPPORTS_CREDENTIALS=false
CORS_MAX_AGE=86400

API_RATE_LIMIT=60
API_RATE_LIMIT_LOGIN=5
API_RATE_LIMIT_REGISTER=3
API_RATE_LIMIT_CONTACT=3
API_RATE_LIMIT_PASSWORD=5
API_RATE_LIMIT_VERIFICATION=3
API_RATE_LIMIT_COMMENT=3
API_RATE_LIMIT_NEWSLETTER=5
```

`config/api.php`: sayfalama tavanı, dışarı açılan ayar grupları ve çeviri
grupları, hız sınırları.

Süresi dolmuş jetonlar haftalık `sanctum:prune-expired` göreviyle temizlenir
(`routes/console.php`). Görev, hosting kısıtı gereği `Schedule::call()` +
`Artisan::call()` ile kurulur — `Schedule::command()` bu sunucuda sessizce hiç
çalışmaz (`docs/SHARED-HOSTING.md`).

---

## Sürümleme

Sürüm adreste taşınır (`/api/v1`). Kırıcı bir değişiklik geldiğinde `/api/v2`
açılır ve v1 bir süre yayında kalır: mobil uygulama mağazadan güncellenene kadar
eski sözleşmeyi konuşmaya devam eder.

Kırıcı sayılan değişiklikler: bir alanın adının ya da tipinin değişmesi, bir
alanın kaldırılması, bir durum kodunun değişmesi, zorunlu bir parametrenin
eklenmesi. **Yeni alan eklemek kırıcı değildir** — istemci tanımadığı alanları
yok saymalıdır.

Sözleşmeyi `tests/Feature/Api/ApiContractTest.php` bekçiliyor: zarf, hata
biçimi, dil çözümü ve CORS başlıkları oradaki sınamalarla sabitlenmiştir.

---

## Dosya haritası

```
routes/api.php                         Uçlar
config/api.php                         Sayfalama, hız sınırı, açılan ayarlar
config/sanctum.php                     Jeton ömrü, stateful alan adları
config/cors.php                        Çapraz kaynak kuralları

app/Http/Controllers/Api/V1/           İnce controller'lar
app/Http/Requests/Api/V1/              Doğrulama
app/Http/Resources/Api/V1/             Dışarı açılan alanlar (beyaz liste)
app/Http/Responses/ApiResponse.php     Yanıt zarfı
app/Exceptions/ApiExceptionRenderer.php Hata zarfı
app/Services/ApiAuthService.php        Oturumsuz kimlik doğrulama
app/Services/PasswordResetCodeService.php  Altı haneli sıfırlama kodu
app/Http/Middleware/SetApiLocale.php   Dil çözümü
app/Http/Middleware/ForceJsonResponse.php
app/Http/Middleware/EnsureApiUserIsActive.php
app/Http/Middleware/EnsureApiEmailIsVerified.php
app/Http/Middleware/EnsureApiIsAvailable.php

tests/Feature/Api/                     102 sınama
```
