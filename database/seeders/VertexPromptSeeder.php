<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\VertexPrompt;
use Illuminate\Database\Seeder;

/**
 * Google Vertex AI — hazır şablonlar seeder.
 *
 * Çalıştırma:
 *   php artisan db:seed --class=Database\\Seeders\\VertexPromptSeeder
 *
 * Idempotent: aynı 'name' varsa skip, yeniden çalıştırmak güvenli.
 * Şablonlardaki {{ozel_gun_mesaji}} ve {{alt_mesaj}} değişkenleri üretim ekranında
 * otomatik tespit edilir, kullanıcı her seferinde doldurur.
 *
 * NOT: Logo referans görseli seeder ile eklenmez — şablonu kaydettikten sonra
 * "Düzenle" ekranından PNG logoyu yükle.
 */
class VertexPromptSeeder extends Seeder
{
    public function run(): void
    {
        $sharedPromptBody = $this->sharedPromptBody();

        $templates = [
            [
                'name'              => 'Özel Gün Hikaye 9:16',
                'description'       => 'Logo + premium özel gün mesajı. Instagram Story/Reels formatı (1080×1920).',
                'aspect_ratio'      => '9:16',
                'image_size'        => '2K',
                'canvas_block'      => "CANVAS:\n- 1080x1920 px\n- 9:16 vertical format\n- DO NOT crop, DO NOT add letterbox\n- Mobile-first composition\n\nLAYOUT STRUCTURE:\n- Top 20%    → logo area (Image 1, centered)\n- Center 60% → main text + sub text\n- Bottom 20% → soft decorative space",
            ],
            [
                'name'              => 'Özel Gün Post 1:1',
                'description'       => 'Logo + premium özel gün mesajı. Instagram Post formatı (1080×1080).',
                'aspect_ratio'      => '1:1',
                'image_size'        => '1K',
                'canvas_block'      => "CANVAS:\n- 1080x1080 px\n- 1:1 square format\n- DO NOT crop, DO NOT add letterbox\n- Feed-first composition\n\nLAYOUT STRUCTURE:\n- Top 25%    → logo area (Image 1, centered)\n- Center 50% → main text + sub text\n- Bottom 25% → soft decorative space",
            ],
        ];

        foreach ($templates as $i => $tpl) {
            $exists = VertexPrompt::where('name', $tpl['name'])->exists();
            if ($exists) {
                $this->command?->info("⊙ Skip (zaten var): {$tpl['name']}");
                continue;
            }

            VertexPrompt::create([
                'name'              => $tpl['name'],
                'description'       => $tpl['description'],
                'prompt'            => $this->buildPrompt($tpl['canvas_block'], $sharedPromptBody),
                'reference_images'  => null, // Logoyu üye olarak elle yükle
                'model'             => 'gemini-3.1-flash-image-preview',
                'aspect_ratio'      => $tpl['aspect_ratio'],
                'image_size'        => $tpl['image_size'],
                'mime_type'         => 'image/png',
                'temperature'       => 1.00,
                'max_output_tokens' => 32768,
                'top_p'             => 0.95,
                'thinking_level'    => 'MINIMAL',
                'person_generation' => 'ALLOW_ALL',
                'safety_off'        => true,
                'sort_order'        => ($i + 1) * 10,
                'is_active'         => true,
            ]);

            $this->command?->info("✓ Eklendi: {$tpl['name']}");
        }
    }

    private function buildPrompt(string $canvasBlock, string $body): string
    {
        return <<<PROMPT
ATTACHED ASSETS (in order — DO NOT swap):
- Image 1 = Brand logo (use at TOP CENTER)

STRICT ASSET PROTECTION MODE:
- Image 1 is a PNG logo with transparent background.
- NEVER add background, glow, shadow, box, or layer behind it.
- NEVER recreate, redraw, restyle, recolor, or modify the logo.
- Preserve transparency exactly.
- Use original file as-is, clean and sharp.
- Stay 100% faithful to the original visual.

STRICT TEXT MODE:
- All text must be written EXACTLY as provided below.
- No spelling mistakes, no missing characters.
- Turkish characters must be correct (ğ, ı, ş, ç, ö, ü, İ).
- Preserve uppercase/lowercase formatting exactly.
- NEVER generate fake words or random text.

{$canvasBlock}

{$body}
PROMPT;
    }

    private function sharedPromptBody(): string
    {
        return <<<'BODY'
TOP AREA:
- Place Image 1 at TOP CENTER
- Logo must remain perfectly clean and readable
- Keep background soft and blurred
- No objects or shapes behind logo
- Maintain strong contrast for visibility

CENTER AREA (MAIN FOCUS):
- Theme: {{ozel_gun_mesaji}}
- Build an emotional, elegant, premium composition related to this theme.
- Cinematic, modern, scroll-stopping social media aesthetic.

SCENE DESIGN (ADAPTIVE — choose by theme):
- National days (23 Nisan, 19 Mayıs, 30 Ağustos, 29 Ekim)
  → respectful atmosphere, subtle Turkish flag tones (red & white accents),
    Atatürk silhouettes only if appropriate, no political slogans
- Religious holidays (Ramazan, Kurban Bayramı)
  → warm, peaceful, spiritual feeling, soft golden light,
    crescents/mosques only as distant silhouettes, no human faces
- Celebration days (Anneler Günü, Babalar Günü, Yılbaşı)
  → joyful and emotional mood, warm bokeh, flowers/gifts allowed
- Professional/special days (24 Kasım Öğretmenler, 1 Mayıs, Sevgililer)
  → elegant and respectful composition

ENVIRONMENT:
- Natural warm lighting
- Cinematic atmosphere
- Soft depth of field, background slightly blurred
- Premium organic brand feeling (Anatolian farm aesthetic if relevant)
- Modern 2026 social media design aesthetic

TEXT PLACEMENT (CRITICAL):

MAIN TEXT (render EXACTLY this string, do not alter):
{{ozel_gun_mesaji}}

SUB TEXT (render EXACTLY this string, do not alter):
{{alt_mesaj}}

PLACEMENT RULES:
- MAIN TEXT placed in the upper-center area of the center band
- SUB TEXT placed directly below MAIN TEXT
- 20–40 px vertical spacing between MAIN TEXT and SUB TEXT
- Both texts center aligned
- Both texts must remain visually separated, never merged into one line
- Text must NOT overlap the logo or any photo subject's face

TEXT HIERARCHY:
- MAIN TEXT = dominant (largest and boldest)
- SUB TEXT = clearly smaller, lighter weight, still very readable

TEXT STYLE:
- Large, bold, premium typography
- White or soft warm cream tone
- Strong shadow or subtle dark outline for readability
- Elegant sans-serif (Poppins / Inter / Montserrat aesthetic)
- Clean overlay look — NOT embedded into photo
- Letters perfectly formed, no broken/overlapping/distorted glyphs

TEXT RULES:
- NEVER rewrite, shorten, paraphrase, translate, or auto-correct the texts
- NEVER merge MAIN TEXT and SUB TEXT into one line
- Preserve Turkish characters exactly (ğ, ı, ş, ç, ö, ü, İ)
- Keep typography clean and sharp
- If you cannot render a character cleanly, do NOT replace it — render exactly

BACKGROUND FOR TEXT:
- Apply a soft vertical gradient overlay (transparent → slight dark)
  ONLY behind the text band if needed for contrast
- Gradient must feel natural and cinematic, never blocky
- Avoid artificial flat overlays or solid color boxes

STYLE:
- Premium organic brand feeling
- Emotional storytelling
- Warm natural tones (soft amber, cream, deep forest green, terracotta)
- Minimal but powerful composition
- Scroll-stopping social media quality

FINAL QUALITY:
- Ultra realistic, cinematic lighting, HDR feeling
- Realistic shadows, clean edges, high detail
- Professional social media design, 8K quality feel

NEGATIVE PROMPT (avoid):
- blurry, low resolution, pixelated
- distorted/broken/misspelled Turkish text
- fake text or random words
- logo distortion, recolored logo, black background behind PNG
- cluttered layout, unreadable typography, weak contrast
- jpeg artifacts, oversharpen, plastic look, noisy image
- inconsistent spacing, badly aligned text
- watermark, URL, phone number, hashtag, placeholder text like [BRACKET]

TEXT RENDERING ENHANCEMENT:
- Typography behaves like a professional graphic overlay
- Text remains crisp and sharp at thumbnail size
- Prioritize readability over artistic blending
- Text feels intentionally designed, not AI-generated

FINAL VERIFICATION:
✓ Correct canvas dimensions, no crop
✓ Logo (Image 1) at top center, clean, no background added
✓ MAIN TEXT exactly: {{ozel_gun_mesaji}}
✓ SUB TEXT exactly: {{alt_mesaj}}
✓ Turkish characters correct
✓ MAIN TEXT and SUB TEXT visually separated
✓ Emotional, premium, cinematic composition
✓ Mobile readability optimized
BODY;
    }
}
