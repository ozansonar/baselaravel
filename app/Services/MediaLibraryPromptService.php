<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Nano Banana (ve benzeri AI görsel modelleri) için ürün-bazlı prompt
 * şablonları. Admin görsel kütüphanesine yükleyeceği görselleri tarayıcıdan
 * üretirken bu prompt'ları kopyala-yapıştır kullanır.
 *
 * İki mod:
 *   MODE_STANDARD        → sade ürün fotoğrafı, yazısız temiz arka plan.
 *                          Kütüphaneye ham görsel yüklemek için.
 *   MODE_BRANDED_OVERLAY → tam paket: logo (top + ürün etiketi) + iletişim
 *                          ikonları + telefon + Instagram handle. Doğrudan
 *                          paylaşıma hazır görsel. 5 PNG asset gerekir
 *                          (01-logo, 02-instagram, 03-facebook, 04-whatsapp,
 *                          05-phone).
 *
 * Sabit data — DB'de tutulmaz, kod içinde gömülü. Yeni ürün eklenirse
 * STANDARD mod'da generic fallback kullanılır.
 */
final class MediaLibraryPromptService
{
    public const string MODE_STANDARD = 'standard';
    public const string MODE_BRANDED_OVERLAY = 'branded_overlay';
    public const string MODE_CTA_STORY = 'cta_story';
    public const string MODE_OZEL_GUN_HIKAYE = 'ozel_gun_hikaye';
    /**
     * Markalı overlay prompt'u — tam paket görsel.
     *
     * Kullanıcı `01-logo.png`, `02-instagram.png`, `03-facebook.png`,
     * `04-whatsapp.png`, `05-phone.png` PNG'lerini Nano Banana'ya attach
     * eder, bu prompt'u yapıştırır. Çıktı doğrudan Instagram/Facebook'a
     * paylaşılabilir markalı görsel.
     *
     * `[ÜRÜN ADI]` placeholder'ı seçili ürünün adıyla değiştirilir.
     */
    private const string BRANDED_OVERLAY_TEMPLATE = <<<EOT
ATTACHED ASSETS (in order — DO NOT swap):
- Image 1 = Brand logo (use at TOP CENTER + as natural product label)
- Image 2 = Instagram icon (BOTTOM-LEFT, position 1)
- Image 3 = Facebook icon (BOTTOM-LEFT, position 2)
- Image 4 = WhatsApp icon (BOTTOM-RIGHT, position 1)
- Image 5 = Phone icon (BOTTOM-RIGHT, position 2)

STRICT ASSET PROTECTION MODE (VERY IMPORTANT):
- I will provide multiple PNG images (brand logo + social media icons).
- These images may have transparent backgrounds.
- NEVER add background to them.
- NEVER place them on black or colored boxes.
- NEVER recreate, redraw, enhance, restyle, or modify them.
- NEVER change colors or contrast of the logo.
- Preserve transparency exactly.
- Use original files as-is, clean and sharp.

CRITICAL ASSET RULE (ABSOLUTE):
- Use the attached images exactly as provided.
- Stay 100% faithful to the original visuals.
- DO NOT alter them in any way.
- DO NOT add any background, effect, glow, shadow, or layer behind them.
- DO NOT reinterpret or recreate them.
- Place them exactly as clean overlay elements.

STRICT TEXT MODE:
- All text must be written EXACTLY as provided
- No spelling mistakes
- No missing or changed characters
- Turkish characters must be correct (ğ, ı, ş, ç, ö, ü)
- Phone format STRICT: "+90 505 942 41 24"
- Username STRICT: "orhanbabaninciftligi"

CANVAS:
- 1500x1500 px
- 1:1 square format
- DO NOT add letterbox bands, DO NOT crop

LAYOUT STRUCTURE:
- Top 25% → logo area
- Center 50% → product
- Bottom 25% → info area

TOP AREA:
- Logo at top center
- Clean blurred background

CENTER AREA:
- Product: [ÜRÜN ADI]
- Natural farm scene
- Warm light, shallow depth of field

PRODUCT LABEL:
- Place Image 1 on product as real label
- Match lighting, perspective, curvature
- No extra text allowed

NO TEXT ON PRODUCT:
- Only logo allowed
- No fake text

BOTTOM AREA:
LEFT:
- Instagram + Facebook icons
- Under text: orhanbabaninciftligi

RIGHT:
- WhatsApp + Phone icons
- Under text: +90 505 942 41 24

TEXT STYLE:
- Bold, large, white
- Shadow or outline
- Mobile readable

BACKGROUND:
- Dark gradient bottom

ICON RULES:
- Same size
- Clean alignment

STYLE:
- Premium farm
- Warm tones
- Modern

FINAL QUALITY:
- Ultra realistic
- Clean overlay design

EXTRA RULE:
- Text and icons must look like overlay layer

-----------------------------------
NEGATIVE PROMPT (CRITICAL):
- blurry, low resolution, pixelated
- distorted text, broken letters, misspelled words
- random text, fake words, gibberish
- logo distortion, logo recolor, logo background added
- black box behind PNG
- icons deformed, inconsistent sizes
- cluttered layout, messy composition
- low contrast text, unreadable typography
- watermark, signature, artifacts
- oversharpen, noise, jpeg artifacts
- unrealistic plastic look
- wrong alignment, uneven spacing

-----------------------------------
RENDER QUALITY BOOST:
- ultra high detail
- sharp focus on product
- cinematic lighting
- physically accurate materials
- high dynamic range (HDR)
- realistic shadows and reflections
- no noise, no grain
- clean edges
- professional product photography
- 8k quality feel

-----------------------------------
TEXT RENDERING ENHANCEMENT:
- Typography must behave like a graphic overlay layer
- NOT embedded into the photo
- Always crisp, sharp, and readable
- Prioritize clarity over artistic blending

FINAL VERIFICATION:
✓ 1500x1500
✓ Logo protected
✓ No text on product
✓ Icons correct order
✓ Text exact
✓ No PNG background issue
✓ Clean layout
EOT;

    /**
     * Instagram Story CTA prompt'u — dikey 1080×1920 (9:16).
     *
     * Kullanıcı sadece `01-logo.png` attach eder. Çıktı: ürün + CTA +
     * telefon numarası içeren Story görseli. `[URUN ADI]` placeholder'ı
     * seçili ürünün adıyla değiştirilir.
     */
    private const string CTA_STORY_TEMPLATE = <<<'EOT'
ATTACHED ASSETS (in order — DO NOT swap):
- Image 1 = Brand logo (use at TOP CENTER + as natural product label)

STRICT ASSET PROTECTION MODE (VERY IMPORTANT):
- I will provide a PNG logo image (Image 1).
- It may have transparent background.
- NEVER add background to it.
- NEVER place it on black or colored boxes.
- NEVER recreate, redraw, enhance, restyle, or modify it.
- NEVER change colors or contrast.
- Preserve transparency exactly.
- Use original file as-is, clean and sharp.

CRITICAL ASSET RULE (ABSOLUTE):
- Use the attached logo exactly as provided.
- Stay 100% faithful to the original visual.
- DO NOT alter it in any way.
- DO NOT add any background, glow, shadow, or layer behind it.
- Place it as a clean overlay element.

STRICT TEXT MODE:
- All text must be written EXACTLY as provided
- No spelling mistakes
- No missing or changed characters
- Turkish characters must be correct (ğ, ı, ş, ç, ö, ü)
- Phone format STRICT: "+90 505 942 41 24"

- CTA TEXT (choose one and use EXACTLY):
  "Sipariş ver"
  "Hemen ara"
  "WhatsApp'tan yaz"
  "Doğal ürünler için bize ulaş"
  "Hemen sipariş oluştur"
  "Taze ürünler için ara"
  "Bugün sipariş ver"
  "Şimdi iletişime geç"
  "Doğallığın tadına var"
  "Köy ürünleri için bize ulaş"
  "Hemen WhatsApp'tan yaz"
  "Sipariş için hemen ara"
  "Doğrudan üreticiden al"
  "Tazeliği şimdi keşfet"
  "En doğal haliyle sipariş ver"

CANVAS:
- 1080x1920 px
- 9:16 vertical format
- DO NOT crop
- DO NOT add letterbox

LAYOUT STRUCTURE:
- Top 20% → logo
- Center 55% → product
- Bottom 25% → contact + CTA

TOP AREA:
- Logo (Image 1) at TOP CENTER
- Clean blurred background
- No objects behind logo

CENTER AREA:
- Product: [URUN ADI]
- Natural farm environment
- Wooden textures
- Warm daylight
- Strong depth of field (product sharp)

PRODUCT LABEL:
- Place small Image 1 on product as real label
- Match lighting and perspective
- No color changes

NO TEXT ON PRODUCT:
- Only logo allowed
- No fake text

BOTTOM AREA (CONVERSION ZONE):

STRUCTURE (VERTICAL STACK):

1. CTA TEXT (TOP of bottom area)
   - Large and bold
   - Example: "Sipariş ver"
   - Center aligned

2. PHONE NUMBER (MIDDLE)
   +90 505 942 41 24
   - Slightly smaller than CTA but still large

3. CTA BUTTON STYLE (BOTTOM)
   - Create a soft rounded button look (visual only)
   - Inside button repeat CTA text (same as above)
   - Button must be subtle, NOT aggressive
   - High contrast and readable

TEXT STYLE:
- Bold, modern sans-serif (Poppins / Inter style)
- White color
- Strong shadow or dark outline
- High readability on mobile

BACKGROUND FOR TEXT:
- Strong dark gradient at bottom (transparent → black 0.85)
- Keep smooth transition

CTA DESIGN RULE:
- CTA must stand out more than phone number
- Clear visual hierarchy:
  CTA > Phone > Background

STYLE:
- Premium organic farm brand
- Warm, natural tones
- Clean, modern (2026 trend)
- Minimal but persuasive

FINAL QUALITY:
- Ultra realistic product
- Clean overlay typography
- Scroll-stopping + conversion focused

-----------------------------------
NEGATIVE PROMPT (CRITICAL):
- blurry, low resolution, pixelated
- distorted text, broken letters
- fake text, random text
- logo distortion, recolor
- black background behind logo
- cluttered layout
- unreadable CTA
- weak contrast
- noise, grain
- jpeg artifacts
- unrealistic plastic look

-----------------------------------
RENDER QUALITY BOOST:
- ultra high detail
- cinematic lighting
- HDR
- realistic shadows
- clean edges
- no noise
- 8k quality feel

-----------------------------------
TEXT RENDERING ENHANCEMENT:
- Text must look like graphic overlay
- NOT embedded into image
- Always crisp and sharp
- Prioritize readability

FINAL VERIFICATION:
✓ 1080x1920
✓ Logo protected
✓ No text on product
✓ CTA clearly visible
✓ Phone correct format
✓ Clean layout
✓ No background behind logo
EOT;

    /**
     * Özel Gün Hikaye prompt'u — 1080x1920 dikey Story formatında, özel
     * gün/bayram/anma günleri için duygusal mesaj + logo overlay.
     *
     * Kullanıcı `01-logo.png` PNG'sini Nano Banana'ya attach eder, bu
     * prompt'u yapıştırır. `[OZEL GUN MESAJI]` ve `[ALT MESAJ]` placeholder'ları
     * runtime'da kullanıcı seçimine göre doldurulur (örn. "24 Kasım Öğretmenler
     * Günü" / "Sevgi ve minnetle").
     *
     * NOWDOC (single-quoted EOT): içeride hiçbir interpolasyon olmaz, prompt
     * AYNEN kayıtlı.
     */
    private const string OZEL_GUN_HIKAYE_TEMPLATE = <<<'EOT'
ATTACHED ASSETS (in order — DO NOT swap):
- Image 1 = Brand logo (use at TOP CENTER)

STRICT ASSET PROTECTION MODE (VERY IMPORTANT):
- I will provide a PNG logo image (Image 1).
- It may have transparent background.
- NEVER add background to it.
- NEVER place it on black or colored boxes.
- NEVER recreate, redraw, enhance, restyle, or modify it.
- NEVER change colors or contrast.
- Preserve transparency exactly.
- Use original file as-is, clean and sharp.

CRITICAL ASSET RULE (ABSOLUTE):
- Use the attached logo exactly as provided.
- Stay 100% faithful to the original visual.
- DO NOT alter it in any way.
- DO NOT add any background, glow, shadow, or layer behind it.
- DO NOT reinterpret or recreate the logo.
- Place it as a clean overlay element.

STRICT TEXT MODE:
- All text must be written EXACTLY as provided
- No spelling mistakes
- No missing or changed characters
- Turkish characters must be correct (ğ, ı, ş, ç, ö, ü)
- Preserve uppercase/lowercase formatting exactly
- NEVER generate fake words or random text

CANVAS:
- 1080x1920 px
- 9:16 vertical format
- DO NOT crop
- DO NOT add letterbox
- Mobile-first composition

LAYOUT STRUCTURE:
- Top 20% → logo area
- Center 60% → main visual + message
- Bottom 20% → soft decorative space

TOP AREA:
- Place Image 1 at TOP CENTER
- Logo must remain perfectly clean and readable
- Keep background soft and blurred
- No objects behind logo
- Maintain strong contrast for visibility

CENTER AREA (MAIN FOCUS):
- Theme: [OZEL GUN MESAJI]
- Create an emotional, elegant and premium composition related to the theme
- Composition must feel cinematic and modern

SCENE DESIGN (ADAPTIVE):
- National days → respectful atmosphere, subtle Turkish flag tones
- Religious holidays → warm, peaceful, spiritual feeling
- Celebration days → joyful and emotional mood
- Professional/special days → elegant and respectful composition

ENVIRONMENT:
- Natural warm lighting
- Cinematic atmosphere
- Soft depth of field
- Background slightly blurred
- Premium organic brand feeling
- Modern 2026 social media design aesthetic

TEXT PLACEMENT:
- Place the MAIN TEXT in the CENTER AREA
- Text must be highly readable on mobile devices
- Use balanced spacing and visual hierarchy

FORMAT:

Main Text:
[OZEL GUN MESAJI]

Sub Text (optional):
[ALT MESAJ]

OPTIONAL SUBTEXT SYSTEM:
- Sub text is OPTIONAL
- If [ALT MESAJ] is empty or not provided:
  Automatically select the MOST RELEVANT sub message
  from the READY MESSAGE PACKAGES below
  according to the selected [OZEL GUN MESAJI]

AUTO MESSAGE SELECTION PRIORITY:
1. Emotional relevance
2. Theme compatibility
3. Readability
4. Visual harmony

PLACEMENT RULES:
- Sub text must appear directly below the main text
- Keep spacing between texts around 20–40 px
- Both texts must remain visually separated
- Center aligned

TEXT HIERARCHY:
- Main text = dominant (largest and boldest)
- Sub text = slightly smaller but still very readable

TEXT STYLE:
- Large, bold, premium typography
- White or soft warm tone
- Strong shadow or subtle dark outline
- Elegant sans-serif style (Poppins / Inter / Montserrat)
- Clean overlay look
- NOT embedded into photo

SUBTEXT STYLE:
- Same font family
- Slightly lighter weight
- High readability
- Strong enough contrast for mobile viewing

TEXT RULES:
- NEVER rewrite the text
- NEVER shorten text
- NEVER paraphrase
- NEVER merge main text and sub text into one line
- Preserve Turkish characters exactly
- Keep typography clean and sharp

BACKGROUND FOR TEXT:
- Use soft gradient if necessary for readability
- Gradient must feel natural and cinematic
- Avoid artificial overlays

STYLE:
- Premium organic brand feeling
- Emotional storytelling
- Warm natural tones
- Minimal but powerful composition
- Scroll-stopping social media quality

FINAL QUALITY:
- Ultra realistic
- Cinematic lighting
- HDR feeling
- Realistic shadows
- Clean edges
- High detail
- Professional social media design
- 8K quality feel

-----------------------------------
NEGATIVE PROMPT (CRITICAL):
- blurry
- low resolution
- pixelated
- distorted text
- broken letters
- misspelled Turkish text
- fake text
- random words
- logo distortion
- recolored logo
- black background behind PNG
- cluttered layout
- unreadable typography
- weak contrast
- jpeg artifacts
- oversharpen
- unrealistic plastic look
- noisy image
- inconsistent spacing
- badly aligned text

-----------------------------------
TEXT RENDERING ENHANCEMENT:
- Typography must behave like a professional graphic overlay
- Text must remain crisp and sharp
- Prioritize readability over artistic blending
- Text should feel intentionally designed
- Ensure readability even in thumbnail preview

-----------------------------------
READY MESSAGE PACKAGES
(If [ALT MESAJ] is empty, automatically choose one suitable message below)

23 Nisan Ulusal Egemenlik ve Çocuk Bayramı
ALT MESAJ OPTIONS:
- Kutlu olsun
- Çocuklarımızın geleceği aydınlık olsun
- Sevgiyle büyüyen nesillere
- Nice mutlu bayramlara

19 Mayıs Atatürk'ü Anma, Gençlik ve Spor Bayramı
ALT MESAJ OPTIONS:
- Minnetle anıyoruz
- Kutlu olsun
- Gençliğe armağan edilen bu özel gün kutlu olsun
- Daima izindeyiz

1 Mayıs Emek ve Dayanışma Günü
ALT MESAJ OPTIONS:
- Emek en yüce değerdir
- Tüm emekçilerimizin günü kutlu olsun
- Alın terine saygıyla
- Birlik ve dayanışmayla

Kurban Bayramı
ALT MESAJ OPTIONS:
- Mübarek olsun
- Bereket, huzur ve sağlık getirsin
- Sevdiklerinizle birlikte nice bayramlara
- Paylaşmanın ve dayanışmanın bayramı kutlu olsun

Ramazan Bayramı
ALT MESAJ OPTIONS:
- Bayramınız mübarek olsun
- Sevdiklerinizle birlikte huzurlu bayramlar
- Tatlı bir bayram diliyoruz
- Nice mutlu bayramlara

24 Kasım Öğretmenler Günü
ALT MESAJ OPTIONS:
- Saygıyla anıyoruz
- İyi ki varsınız
- Geleceğimizi aydınlatan tüm öğretmenlerimize teşekkür ederiz
- Minnet ve saygıyla

Anneler Günü
ALT MESAJ OPTIONS:
- Tüm annelerimizin günü kutlu olsun
- Sevginiz her şeyden değerli
- İyi ki varsınız
- Kalbimizdeki en özel yer sizin

30 Ağustos Zafer Bayramı
ALT MESAJ OPTIONS:
- Kutlu olsun
- Gurur ve minnetle
- Zaferin ışıltısıyla
- Şanlı tarihimize saygıyla

29 Ekim Cumhuriyet Bayramı
ALT MESAJ OPTIONS:
- Kutlu olsun
- Cumhuriyetimizin ışığında
- Nice yıllara
- Gururla kutluyoruz

-----------------------------------
EXAMPLE USAGE:

[OZEL GUN MESAJI] = 24 Kasım Öğretmenler Günü
[ALT MESAJ] = Saygıyla anıyoruz

OR

[OZEL GUN MESAJI] = 29 Ekim Cumhuriyet Bayramı
[ALT MESAJ] =

(If empty → auto select appropriate sub text)

-----------------------------------
FINAL VERIFICATION:
✓ 1080x1920 vertical format
✓ Logo fully protected
✓ No background behind logo
✓ Text perfectly readable
✓ Turkish characters correct
✓ Main text and sub text separated
✓ Emotional and premium composition
✓ Mobile readability optimized
✓ No fake text generated
✓ Clean cinematic design
EOT;

    /**
     * Her prompt'a eklenen ortak kurallar — Nano Banana'nın kare üretmesini
     * ve YAZI/LOGO basmamasını garanti eder.
     */
    private const string SHARED_RULES = <<<EOT
KARE FORMAT, 1:1 oranı, 1500×1500 piksel.
Konu MERKEZ %50'de yer alsın (kenar dolgusu için boşluk bırak).
Üst ve alt %25'lik alan dekoratif (yumuşak bokeh, sahne genişlemesi).
KESİNLİKLE YAZMA: text, harfler, rakamlar, başlıklar, watermark,
URL, telefon, hashtag, placeholder ([NUMARANIZ] vb.), buton, rozet.
Saf görsel — yazısız, logosuz, temiz arka plan üretici.
Stil: rustik Anadolu çiftliği, doğal gün ışığı, sıcak toprak tonları,
shallow depth of field, food photography, fotojenik ama doğal.
EOT;

    /**
     * Bilinmeyen ürün için fallback prompt (ürün adı dinamik).
     */
    private const string GENERIC_PROMPT = <<<EOT
Kare 1500×1500 görsel. {product_name} — Türkiye köy yapımı, doğal,
çiftlikte üretilen ürün. Rustik ahşap masa veya köy mutfağı ortamında.
Yanında uygun yan ürünler (taze ekmek, otlar, çay bardağı vb.).
Arka plan: yumuşak bulanık çiftlik manzarası veya doğal pencere ışığı.
EOT;

    /**
     * "Genel" havuz prompt'u — ürün-bağımsız fallback için.
     */
    private const string GENERAL_PROMPT = <<<EOT
Kare 1500×1500 görsel. Anadolu çiftliği genel sahnesi.
Konular (her prompt'ta tek bir konu seç):
- İnek/koyun otlatma manzarası (uzaktan)
- Buğday tarlası, tahıl başakları
- Köy evi (taş duvar, ahşap kapı)
- Ahşap çiftlik araç-gereci (yayık, sepet, çapa)
- Sebze bahçesi
- Tarla çiçekleri (papatya, gelincik)
- Köy avlusu, tavuklar
- Saman balyaları, tarla
- Mevsim sahneleri (bahar yeşilliği, yaz hasadı, sonbahar yaprakları, kış karı)
EOT;

    /**
     * Ürün-spesifik prompt'lar + varyasyon önerileri.
     *
     * @var array<string, array{prompt: string, variations: list<string>}>
     */
    private const array TEMPLATES = [
        'Günlük Taze Süt' => [
            'prompt' => <<<EOT
Kare 1500×1500 görsel. Cam süt şişesi (eski tip, kapaksız veya bez bağlı),
içinde taze köy sütü. Rustik ahşap masa üstünde.
EOT,
            'variations' => [
                'Yanında bir kase yulaf, sabah ışığı vurgu',
                'Tek başına şişe close-up, yumuşak bokeh arka plan',
                'Sürahide süt + cam bardak, kırsal mutfak',
                'Sütçü bidonu + cam bardak, ahşap raf',
                'Süt sağma sahnesi (uzaktan, soyut)',
                'Kahve veya çay yanında süt servisi',
                'Tarçınlı süt köpüğü, sıcak içecek',
            ],
        ],
        'Doğal Köy Tereyağı' => [
            'prompt' => <<<EOT
Kare 1500×1500 görsel. Köy tereyağı bloğu — sarı, doğal, ev yapımı görünüm.
Ahşap kesme tahtası veya rustik tabak üstünde.
EOT,
            'variations' => [
                'Tereyağı bloğu + ahşap bıçak (close-up)',
                'Ekmek üstüne sürülmüş tereyağı, sıcak ekmek dilimi',
                'Kahvaltı sofrasında tereyağı tabağı, bal kavanozu yan',
                'Tereyağı yapım sahnesi (yayık, soyut)',
                'Kavanozda tereyağı + sıcak ekmek',
                'Taze otlarla (kekik, biberiye) servis edilen tereyağı',
            ],
        ],
        'Doğal Köy Peyniri' => [
            'prompt' => <<<EOT
Kare 1500×1500 görsel. Beyaz peynir veya kaşar (orta sertlik) — köy yapımı,
doğal sarımsı/beyaz renk. Ahşap kesme tahtası üstünde dilimlenmiş halde.
EOT,
            'variations' => [
                'Peynir blok + bıçak, basit kompozisyon',
                'Dilimlenmiş peynir + zeytin tabağı',
                'Peynir tabağı + meze düzeni (tam meze)',
                'Peynir + ekmek + çay (kahvaltı)',
                'Peynir yapım sahnesi (telleme, soyut)',
                'Salata içinde peynir, taze sebzelerle',
            ],
        ],
        'Doğal Köy Yoğurdu' => [
            'prompt' => <<<EOT
Kare 1500×1500 görsel. Çanak/seramik tabakta köy yoğurdu — kremamsı yüzey,
doğal süt yağ izi. Üstünde ahşap kaşık.
EOT,
            'variations' => [
                'Sade yoğurt + ahşap kaşık close-up',
                'Yoğurt + bal + ceviz (kahvaltı kase)',
                'Yoğurt + meyve (çilek, böğürtlen)',
                'Yoğurt çorbası servisi (içi yoğurt)',
                'Yoğurt yapımı (mayalama, soyut)',
                'Cacık servisi, yaz tabağı',
            ],
        ],
        'Doğal Çökelek' => [
            'prompt' => <<<EOT
Kare 1500×1500 görsel. Çökelek peyniri — beyaz, dağılgan tekstür, köy
yapımı görünüm. Seramik tabakta veya ahşap kase içinde. Üzerinde taze
nane veya kekik dalları.
EOT,
            'variations' => [
                'Çökelek tabağı + sebze + zeytinyağı şişesi',
                'Çökelek + ekmek + kahvaltı sofrası',
                'Çökelek + roka veya maydanoz salatası',
                'Çökelek yapım sahnesi (süzme, soyut)',
                'Çökelek kavanozu, raf üstünde',
            ],
        ],
        'Gezen Tavuk Yumurtası' => [
            'prompt' => <<<EOT
Kare 1500×1500 görsel. Köy yumurtası — kahverengi/krem renk, doğal kabuk
dokusu. Hasır sepet veya seramik tabakta. Yanında taze yulaf, saman,
veya tarla çiçekleri.
EOT,
            'variations' => [
                'Sepet içinde yumurta + saman, doğal aydınlatma',
                'Tek yumurta close-up (saman zemin)',
                'Sahanda yumurta (kahvaltı, taze taze)',
                'Çırpılmış yumurta (omlet hazırlığı)',
                'Yumurta + ekmek + tereyağı (klasik kahvaltı)',
                'Tavuk + yumurta sahnesi (uzaktan, soyut)',
                'Kırılmış yumurta sarısı close-up, parlak',
            ],
        ],
    ];

    /**
     * Belirli bir ürün için tüm prompt verilerini döner — UI dropdown ve
     * preview için. Mode'a göre yapı değişir:
     *
     *   STANDARD       → base_prompt + variations + shared_rules
     *   BRANDED_OVERLAY → tek büyük prompt (variations YOK), [ÜRÜN ADI]
     *                     placeholder seçili ürünle değiştirilir
     *   CTA_STORY       → dikey Story prompt (variations YOK), [URUN ADI]
     *                     placeholder seçili ürünle değiştirilir
     *
     * @return array{
     *     product_name: string,
     *     mode: string,
     *     base_prompt: string,
     *     variations: list<string>,
     *     shared_rules: string,
     * }
     */
    public function getForProduct(?string $productName, string $mode = self::MODE_STANDARD): array
    {
        $mode = match ($mode) {
            self::MODE_BRANDED_OVERLAY => self::MODE_BRANDED_OVERLAY,
            self::MODE_CTA_STORY       => self::MODE_CTA_STORY,
            self::MODE_OZEL_GUN_HIKAYE => self::MODE_OZEL_GUN_HIKAYE,
            default                    => self::MODE_STANDARD,
        };

        // ─── Markalı Overlay modu ───
        // Tek büyük prompt; variation/shared_rules yok (zaten template içinde).
        if ($mode === self::MODE_BRANDED_OVERLAY) {
            $productLabel = ($productName === null || $productName === '' || $productName === 'Genel')
                ? 'Anadolu çiftlik ürünü (genel)'
                : $productName;

            return [
                'product_name' => $productLabel,
                'mode'         => self::MODE_BRANDED_OVERLAY,
                'base_prompt'  => str_replace('[ÜRÜN ADI]', $productLabel, self::BRANDED_OVERLAY_TEMPLATE),
                'variations'   => [],
                'shared_rules' => '',
            ];
        }

        // ─── CTA Story modu ───
        if ($mode === self::MODE_CTA_STORY) {
            $productLabel = ($productName === null || $productName === '' || $productName === 'Genel')
                ? 'Anadolu çiftlik ürünü (genel)'
                : $productName;

            return [
                'product_name' => $productLabel,
                'mode'         => self::MODE_CTA_STORY,
                'base_prompt'  => str_replace('[URUN ADI]', $productLabel, self::CTA_STORY_TEMPLATE),
                'variations'   => [],
                'shared_rules' => '',
            ];
        }

        // ─── Özel Gün Hikaye modu ───
        // Prompt'un kendisi AYNEN korunur — [OZEL GUN MESAJI] ve [ALT MESAJ]
        // placeholder'larını kullanıcı runtime'da Nano Banana'ya yapıştırırken
        // kendi seçtiği mesajla değiştirir (ürün-bağımsız: özel gün/bayram/anma).
        if ($mode === self::MODE_OZEL_GUN_HIKAYE) {
            return [
                'product_name' => 'Özel Gün',
                'mode'         => self::MODE_OZEL_GUN_HIKAYE,
                'base_prompt'  => self::OZEL_GUN_HIKAYE_TEMPLATE,
                'variations'   => [],
                'shared_rules' => '',
            ];
        }

        // ─── Standart mod ───
        // "Genel" havuz veya null → genel prompt
        if ($productName === null || $productName === '' || $productName === 'Genel') {
            return [
                'product_name' => 'Genel',
                'mode'         => self::MODE_STANDARD,
                'base_prompt'  => self::GENERAL_PROMPT,
                'variations'   => [],
                'shared_rules' => self::SHARED_RULES,
            ];
        }

        // Bilinen ürün şablonu varsa onu döndür
        if (isset(self::TEMPLATES[$productName])) {
            $template = self::TEMPLATES[$productName];

            return [
                'product_name' => $productName,
                'mode'         => self::MODE_STANDARD,
                'base_prompt'  => $template['prompt'],
                'variations'   => $template['variations'],
                'shared_rules' => self::SHARED_RULES,
            ];
        }

        // Bilinmeyen ürün → generic fallback (ürün adı placeholder ile)
        return [
            'product_name' => $productName,
            'mode'         => self::MODE_STANDARD,
            'base_prompt'  => str_replace('{product_name}', $productName, self::GENERIC_PROMPT),
            'variations'   => [],
            'shared_rules' => self::SHARED_RULES,
        ];
    }

    /**
     * Final kopyalanabilir prompt metni — Nano Banana'ya yapıştırmak için
     * hazır.
     *
     * @param  ?string  $variation  Sadece STANDARD mod'da kullanılır;
     *                              BRANDED_OVERLAY'de yok sayılır.
     */
    public function buildFinalPrompt(
        ?string $productName,
        ?string $variation = null,
        string $mode = self::MODE_STANDARD,
    ): string {
        $data = $this->getForProduct($productName, $mode);

        if ($data['mode'] === self::MODE_BRANDED_OVERLAY
            || $data['mode'] === self::MODE_CTA_STORY
            || $data['mode'] === self::MODE_OZEL_GUN_HIKAYE
        ) {
            return trim($data['base_prompt']);
        }

        // Standart mod: base + variation + shared rules
        $parts = [trim($data['base_prompt'])];

        if ($variation !== null && $variation !== '') {
            $parts[] = trim($variation);
        }

        $parts[] = trim($data['shared_rules']);

        return implode("\n\n", $parts);
    }
}
