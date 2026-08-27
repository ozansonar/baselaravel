---
name: form-validation
description: >
  Admin panelinde form alanı doğrulama kuralları ve giriş maskeleri. Bu skill'i
  şu durumlarda kullan: form yapma, forma alan ekleme, input/select/textarea
  ekleme, doğrulama kuralı seçme, "hangi validate kuralını kullanmalıyım",
  jQuery Validation Engine, data-validation-engine, data-fv-mask, FormRequest
  ile istemci kuralını eşleştirme, mevcut formları kurallar açısından denetleme.
  "form", "alan", "input", "doğrulama", "validation", "kural", "maske", "mask",
  "required", "maxSize", "custom[", "FormRequest", "sadece harf", "sadece sayı"
  gibi ifadelerde tetiklen.
---

# Form Doğrulama — Kural Seçme Rehberi

Formdan gelen veri **üç katmanda** denetlenir. Üçü de zorunludur, biri
diğerinin yerini almaz:

| Katman | Nerede | Ne yapar | Atlanabilir mi |
|---|---|---|---|
| Maske | `data-fv-mask` | Yanlış karakteri yazdırmaz | Alan türü izin veriyorsa evet |
| Kural | `data-validation-engine` | Gönderimde denetler, hatayı gösterir | **Hayır** |
| FormRequest | `app/Http/Requests/` | Son söz, güvenlik sınırı | **Hayır** |

İstemci tarafı kullanıcı deneyimi içindir; **güvenlik yalnızca FormRequest'tedir.**
İstemcideki kural sunucudakinden gevşek olamaz.

---

## 1. Formu devreye alma

```blade
<form method="POST" action="..." data-validate novalidate>
```

`data-validate` olmadan alanlardaki kurallar çalışmaz. `novalidate` tarayıcının
kendi doğrulamasını kapatır — gizli dil sekmesindeki boş bir `required` alan
tarayıcıyı kimsenin göremediği bir mesajla kilitliyor.

---

## 2. Kural seçimi

### Metin

| Alan | Kural |
|---|---|
| Ad, soyad, şehir, ülke (yalnızca harf) | `validate[required,custom[letters],maxSize[100]]` |
| Başlık, açıklama (serbest metin) | `validate[required,maxSize[191]]` |
| İsteğe bağlı serbest metin | `validate[maxSize[500]]` |
| Slug | `validate[required,custom[slug],maxSize[191]]` |
| Şifre | `validate[required,minSize[8],maxSize[191]]` |
| Şifre tekrarı | `validate[required,equals[password]]` |

`custom[letters]` bu projeye özel: Türkçe harfleri kabul eder. Yerleşik
`onlyLetterSp` yalnızca ASCII bilir, "Ömer" ya da "Çağla" reddedilir.

### Sayı

| Alan | Kural |
|---|---|
| Tam sayı | `validate[required,custom[integer]]` |
| Aralıklı tam sayı (yaş, sıra) | `validate[required,custom[integer],min[0],max[120]]` |
| Ondalık (fiyat, oran) | `validate[required,custom[number],min[0]]` |

`min[]`/`max[]` **değeri** sınırlar; `minSize[]`/`maxSize[]` **karakter sayısını**.
Karıştırmak yıllık bir alanı "en fazla 4 karakter" diye sınırlamaya benzer.

### İletişim ve adres

| Alan | Kural |
|---|---|
| E-posta | `validate[required,custom[email],maxSize[191]]` |
| İsteğe bağlı e-posta | `validate[custom[email],maxSize[191]]` |
| Telefon | `validate[required,custom[phone]]` |
| URL | `validate[required,custom[url],maxSize[255]]` |
| Site içi yol (`/hakkimizda`) | `validate[required,custom[sitePath],maxSize[255]]` |
| IP adresi | `validate[custom[ipv4]]` |
| Dil kodu (tr, en) | `validate[required,custom[langCode]]` |

### Tarih

| Alan | Kural |
|---|---|
| Tarih | `validate[required,custom[date]]` |
| Geçmiş bir tarih | `validate[required,custom[date],past[now]]` |
| Gelecek bir tarih | `validate[required,custom[date],future[now]]` |

### Seçim ve dosya

| Alan | Kural |
|---|---|
| Zorunlu `<select>` | `validate[required]` |
| En az bir onay kutusu | `validate[minCheckbox[1]]` |
| En fazla n onay kutusu | `validate[maxCheckbox[n]]` |
| Görsel | `validate[funcCall[FormValidation.rules.imageFile]]` |

Görsel alanı sınırlarını kendi üstünden okur:

```blade
<input type="file" name="cover"
       data-validation-engine="validate[funcCall[FormValidation.rules.imageFile]]"
       data-max-size="2" data-accept="image/jpeg,image/png">
```

---

## 3. Giriş maskesi

```blade
<input type="text" name="first_name"
       data-validation-engine="validate[required,custom[letters],maxSize[100]]"
       data-fv-mask="letters">
```

| Maske | Geçirdiği |
|---|---|
| `letters` | Harf ve boşluk (Türkçe dâhil) |
| `digits` | Yalnızca rakam |
| `decimal` | Rakam ve tek nokta; virgül noktaya çevrilir |

Maske desenleri `custom[letters]`, `custom[integer]` ve `custom[number]` ile
**birebir aynı** tutulur. Maskenin geçirdiği bir değeri kural reddederse
kullanıcı düzeltemeyeceği bir hataya bakar.

Maske belge düzeyinde dinlenir; sonradan JS ile eklenen satırlar (tekrarlanan
alan grupları) ayrıca bağlanmak zorunda değildir.

**Maske kuralın yerine geçmez.** Boş bırakmayı, uzunluğu ve aralığı bilmez;
ayrıca yapıştırma dışı yollarla (otomatik doldurma, geliştirici konsolu) değer
girilebilir.

---

## 4. Kuralsız alan

Kullanıcının veri girdiği her alan ya kural taşır ya da:

```blade
<input type="text" name="not" data-fv-ignore>
```

`data-fv-ignore` işlevsel değildir — motor zaten kuralı olmayan alanı atlar.
Amacı **kararı görünür kılmak**: kuralsız bir alan ya bilinçli bir tercihtir ya
da unutulmuştur, ikisi arasındaki farkı bu işaret söyler. Denetlerken
"kuralı da işareti de olmayan alan" aranır.

Şu alanlar işarete gerek duymaz: `type="hidden"`, `type="submit"`,
CSRF alanı ve `@method` alanı.

---

## 5. Sunucu tarafıyla eşleştirme

İstemcideki her sınır FormRequest'te birebir karşılanır:

```php
'first_name' => ['required', 'string', 'max:100', 'regex:/^[\p{L}\s]+$/u'],
'age'        => ['required', 'integer', 'min:0', 'max:120'],
'email'      => ['required', 'email', 'max:191'],
```

```blade
<input data-validation-engine="validate[required,custom[letters],maxSize[100]]" data-fv-mask="letters">
<input data-validation-engine="validate[required,custom[integer],min[0],max[120]]" data-fv-mask="digits">
<input data-validation-engine="validate[required,custom[email],maxSize[191]]">
```

`maxSize[n]` ile `max:n` **aynı sayı** olur. Farklı olurlarsa kullanıcı formu
geçer, sunucu reddeder ve hata mesajı alanın yanında değil sayfanın başında
belirir.

---

## 6. Hata mesajının yerini değiştirme

Motor mesajı alanın yanına yazar. Alan görünmüyorsa (zengin metin düzenleyici
textarea'yı gizler) mesaja kendi yeri verilir:

```blade
<textarea id="body" name="body"
          data-validation-engine="validate[required]"
          data-prompt-target="body_error"></textarea>
<div id="body_error"></div>
```

Forma değil de tek bir alana ait olmayan bir hata (ör. "en az bir dil
doldurun") ayrıca modal olarak da gösterilmeli:

```blade
<input type="hidden" data-validation-engine="validate[required]"
       data-prompt-target="lang_error" data-fv-modal>
```

---

## 7. Yeni özel kural ekleme

`public/assets/admin/js/form-validation.js` → `CUSTOM RULES` bölümü:

```js
allRules.tcKimlik = {
    regex: /^[1-9][0-9]{10}$/,
    alertText: '11 haneli olmalı ve 0 ile başlamamalı'
};
```

Kullanımı: `validate[required,custom[tcKimlik]]`

Regex ile anlatılamayan bir kural için fonksiyon yazılır:

```js
FormValidation.rules.tcKimlik = function ($field) {
    if (!kontrol($field.val())) {
        return 'Geçersiz T.C. kimlik numarası';
    }
};
```

Kullanımı: `validate[funcCall[FormValidation.rules.tcKimlik]]`

Her yeni kuralın FormRequest'te bir karşılığı olmalı.

---

## 8. Biçim kuralı ne zaman konur

Biçim kuralı (`custom[email]`, `custom[url]`, `custom[phone]`…) **yalnızca
sunucu da o biçimi zorunlu tutuyorsa** konur. Sunucunun kabul ettiği bir değeri
istemcinin reddetmesi, çalışan bir sayfayı bozar: kullanıcı ilgisiz bir alanı
değiştirmek için formu açar ve eski bir değerin kuralı yüzünden kaydedemez.

İki somut örnek:

- **Telefon:** motorun deseni `0212 555 00 00 / 123` gibi dahili ya da ikinci
  numarayı reddediyor. Ayarlardaki telefon alanları serbest metin
  (`nullable|string`), bu yüzden orada `maxSize` var, `custom[phone]` yok.
- **Ad alanı:** `custom[letters]` nokta ve tireyi reddeder. Kullanıcı
  ad/soyad alanlarında bu doğru, çünkü FormRequest'te birebir aynı regex var.
  "Dr. Ahmet" yazılabilen bir ekip üyesi alanında ise yanlış — orada `maxSize`
  kullanılır.

Kısacası: sunucuda karşılığı olmayan bir biçim kuralı koymadan önce, o alana
bugüne kadar ne yazılmış olabileceğini düşün.

## 9. Denetim

Kuralı ve işareti olmayan alanları bulmak için:

```bash
grep -rn "<input\|<select\|<textarea" resources/views/admin \
  | grep -v "data-validation-engine\|data-fv-ignore\|type=\"hidden\"\|type=\"submit\""
```

Bu grep iki durumu kaçırır, sonucu gözle doğrula:

- **Çok satıra yayılan etiketler** — nitelikler alt satırdaysa eşleşmez.
- **`@disabled(...)` / `{{ ... 'disabled' }}` taşıyan alanlar** — "disabled"
  kelimesi Blade ifadesinin içinde geçtiği için alan atlanmış görünür, oysa
  çalışma zamanında etkin olabilir.

Kesin sonuç için sayfayı açıp tarayıcıda saymak en güvenilir yol:

```js
[...document.querySelectorAll('input,select,textarea')].filter(e =>
  !['hidden','submit','button','reset'].includes(e.type) &&
  !e.hasAttribute('data-validation-engine') && !e.hasAttribute('data-fv-ignore') &&
  !e.readOnly && !e.disabled).map(e => e.name || e.id)
```

Döngüyle üretilen alanları (çeviri anahtarları, izin matrisi) yalnızca bu
yöntem görür.

---

## Dikkat

- `min[]`/`max[]` değer, `minSize[]`/`maxSize[]` uzunluk sınırıdır
- Çok dilli formda yalnızca açık sekme denetlenir; gizli sekmelerin kuralları
  gönderim sırasında kaldırılır → `form-validation.js` içindeki
  `scopeRulesToActivePane`
- `type="number"` kullanma; tarayıcı kendi denetimini devreye sokar. Metin alanı
  + `custom[integer]` + `data-fv-mask="digits"` kullan
- Select2'ye çevrilen `<select>` alanında kural asıl `<select>` üzerinde durur
- Zengin metin düzenleyici içeriği gönderimden önce textarea'ya yazılır; kural
  textarea üzerinde tanımlanır
