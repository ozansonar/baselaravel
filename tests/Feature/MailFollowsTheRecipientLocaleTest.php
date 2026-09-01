<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\BlogCommentApprovedMail;
use App\Mail\ContactMessageReplyMail;
use App\Mail\WelcomeMail;
use App\Models\BlogCategory;
use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\ContactMessage;
use App\Models\Language;
use App\Models\MailTemplate;
use App\Models\User;
use App\Services\LanguageService;
use App\Services\MailTemplateService;
use App\Support\MailTemplateDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Gönderilen mail alıcının dilinde yazılmalı.
 *
 * mail_templates anahtar başına tek satır tutuyordu ve BaseMail bir şablon
 * bulduğunda Blade karşılığını hiç çizmiyor. Sonuç: /en'de gezinen bir
 * ziyaretçi kaydolduğunda karşılama maili Türkçe geliyordu — üstelik
 * yöneticinin panelden düzeltebileceği bir yer de yoktu, çünkü tablo tek
 * diliydi.
 *
 * Buradaki testler üç ayrı yolu ayrı ayrı tutuyor: panel şablonu, Blade
 * karşılığı ve kuyruğa alınan mail. Üçü de aynı sebeple Türkçeye düşüyordu ama
 * birini düzeltmek ötekini düzeltmiyor.
 */
final class MailFollowsTheRecipientLocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ön yüz iki dilli olmadan sınanacak bir şey yok.
        foreach ([['tr', true], ['en', false]] as [$code, $isDefault]) {
            Language::firstOrCreate(
                ['code' => $code],
                ['name' => strtoupper($code), 'is_active' => true, 'is_default' => $isDefault],
            );
        }
    }

    private function user(): User
    {
        return User::create([
            'first_name' => 'Ada',
            'last_name'  => 'Lovelace',
            'email'      => 'ada@example.com',
            'password'   => 'password',
            'is_active'  => true,
        ]);
    }

    // ── Panel şablonu ──

    public function test_the_panel_template_of_the_recipients_language_is_used(): void
    {
        app()->setLocale('en');

        $html = (new WelcomeMail($this->user()))->render();

        $this->assertStringContainsString('Welcome', $html);
        $this->assertStringNotContainsString('Hoş Geldiniz', $html);
    }

    public function test_the_subject_follows_the_recipients_language(): void
    {
        app()->setLocale('en');

        $mail = new WelcomeMail($this->user());
        $mail->render();

        $this->assertStringContainsString('Welcome', (string) $mail->subject);
    }

    public function test_the_default_language_still_gets_its_own_text(): void
    {
        app()->setLocale('tr');

        $html = (new WelcomeMail($this->user()))->render();

        $this->assertStringContainsString('Hoş Geldiniz', $html);
    }

    /**
     * Sonradan eklenen bir dilin şablonu henüz yoksa mail yine gitmeli.
     *
     * Alternatif sessizce Blade karşılığına düşmekti; o da ikinci bir metin
     * kaynağı demek — yönetici panelde gördüğü metni değil başka bir metni
     * göndermiş olurdu.
     */
    public function test_a_language_without_a_row_falls_back_to_the_default_language(): void
    {
        MailTemplate::where('key', 'welcome')->where('locale', 'en')->delete();

        app()->setLocale('en');

        $html = (new WelcomeMail($this->user()))->render();

        $this->assertStringContainsString('Hoş Geldiniz', $html);
    }

    // ── Blade karşılığı ──

    public function test_the_blade_fallback_also_follows_the_locale(): void
    {
        MailTemplate::where('key', 'welcome')->delete();

        $user = $this->user();

        app()->setLocale('en');
        $en = (new WelcomeMail($user))->render();

        app()->setLocale('tr');
        $tr = (new WelcomeMail($user))->render();

        $this->assertStringContainsString('Welcome', $en);
        $this->assertStringNotContainsString('Hoş Geldiniz', $en);
        $this->assertStringContainsString('Hoş Geldiniz', $tr);
    }

    // ── Kuyruk ──

    /**
     * Kuyruğa alınan mail işçide çiziliyor ve orada istek dili yok.
     *
     * Dil gönderim anında sorulsaydı config('app.locale') geçerli olurdu, yani
     * her kuyruklanmış mail Türkçe giderdi. Mühür kuruluş anında vuruluyor.
     */
    public function test_a_queued_mail_remembers_the_language_it_was_queued_in(): void
    {
        // Mail::fake() değil Queue::fake(): sahte mailer maili kuyruğa hiç
        // vermiyor, dolayısıyla dilin mühürlendiği yeri de atlıyor. Sınanmak
        // istenen tam olarak o adım.
        Queue::fake();

        app()->setLocale('en');
        Mail::to('ada@example.com')->queue(new WelcomeMail($this->user()));

        Queue::assertPushed(
            SendQueuedMailable::class,
            static fn (SendQueuedMailable $job): bool => $job->mailable->locale === 'en',
        );
    }

    // ── Panelden tetiklenen mailler ──

    /**
     * Panel Türkçeye sabit (SetAdminLocale), ama alıcı öyle olmak zorunda
     * değil: yorumu İngilizce sayfada yazmış olabilir.
     */
    public function test_an_approved_comment_mail_follows_the_articles_language(): void
    {
        $category = BlogCategory::create(['name' => 'News', 'slug' => 'news', 'locale' => 'en']);

        $post = BlogPost::create([
            'blog_category_id' => $category->id,
            'locale'           => 'en',
            'title'            => 'Hello',
            'slug'             => 'hello',
            'body'             => 'Body',
            'status'           => 'published',
        ]);

        $comment = BlogComment::create([
            'blog_post_id' => $post->id,
            'name'         => 'Ada',
            'email'        => 'ada@example.com',
            'body'         => 'Nice article',
            'status'       => 'approved',
        ]);

        // Panelin dili; alıcının dili yazıdan gelmeli.
        app()->setLocale('tr');

        $html = (new BlogCommentApprovedMail($comment))->render();

        $this->assertStringContainsString('Your comment', $html);
        $this->assertStringNotContainsString('Yorumunuz Yayınlandı', $html);
    }

    public function test_a_contact_reply_follows_the_language_the_message_was_sent_in(): void
    {
        $message = ContactMessage::create([
            'name'    => 'Ada',
            'email'   => 'ada@example.com',
            'subject' => 'Question',
            'message' => 'Hello there',
            'locale'  => 'en',
        ]);

        app()->setLocale('tr');

        $html = (new ContactMessageReplyMail($message, 'Here is the answer'))->render();

        $this->assertStringContainsString('reply', strtolower($html));
        $this->assertStringNotContainsString('Mesajınıza Yanıt', $html);
    }

    // ── Panel tarafı ──

    public function test_every_language_gets_a_row_for_every_template(): void
    {
        $keys = MailTemplate::query()
            ->where('locale', app(LanguageService::class)->defaultCode())
            ->pluck('key');

        foreach (Language::pluck('code') as $code) {
            foreach ($keys as $key) {
                $this->assertDatabaseHas('mail_templates', ['key' => $key, 'locale' => $code]);
            }
        }
    }

    public function test_adding_a_language_opens_its_template_rows(): void
    {
        $language = app(LanguageService::class)->create([
            'code'      => 'de',
            'name'      => 'Deutsch',
            'is_active' => true,
        ]);

        $this->assertSame(
            MailTemplate::where('locale', app(LanguageService::class)->defaultCode())->count(),
            MailTemplate::where('locale', $language->code)->count(),
        );
    }

    /**
     * İkinci kez çağrılmak yöneticinin yazdığını silmemeli.
     */
    public function test_syncing_a_language_twice_does_not_overwrite_edits(): void
    {
        MailTemplate::where('key', 'welcome')->where('locale', 'en')->update(['subject' => 'Edited by hand']);

        app(MailTemplateService::class)->syncLocale('en');

        $this->assertSame(
            'Edited by hand',
            MailTemplate::where('key', 'welcome')->where('locale', 'en')->value('subject'),
        );
    }

    /**
     * Her şablonun her iki dilde de aynı değişkenleri kullanması gerekiyor:
     * çeviride unutulan bir {user_name} maili "Merhaba ," diye başlatır.
     */
    public function test_both_shipped_languages_use_the_same_placeholders(): void
    {
        $tr = MailTemplateDefaults::forLocale('tr');
        $en = MailTemplateDefaults::forLocale('en');

        $this->assertSame(array_keys($tr), array_keys($en));

        foreach ($tr as $key => $turkish) {
            $this->assertSame(
                $this->placeholders($turkish),
                $this->placeholders($en[$key]),
                "{$key} şablonunun iki dili aynı değişkenleri kullanmıyor.",
            );
        }
    }

    /**
     * @param  array{subject: string, body: string} $template
     * @return list<string>
     */
    private function placeholders(array $template): array
    {
        preg_match_all('/\{[a-z_]+\}/', $template['subject'] . $template['body'], $matches);

        $found = array_values(array_unique($matches[0]));
        sort($found);

        return $found;
    }
}
