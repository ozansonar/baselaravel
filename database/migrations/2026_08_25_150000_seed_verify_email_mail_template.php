<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the e-mail verification template so it shows up in the mail templates
 * screen like every other mail and stays editable without touching code.
 */
return new class extends Migration
{
    private const KEY = 'verify_email';

    public function up(): void
    {
        if (DB::table('mail_templates')->where('key', self::KEY)->exists()) {
            return;
        }

        $now = now();

        DB::table('mail_templates')->insert([
            'key'         => self::KEY,
            'name'        => 'E-posta Doğrulama',
            'description' => 'Yeni kayıt sonrası e-posta adresini doğrulamak için gönderilen bağlantı.',
            'subject'     => 'E-posta Adresinizi Doğrulayın - {site_name}',
            'body'        => '<p class="em-greeting">Merhaba</p>
<h1 class="em-heading">E-posta Adresinizi Doğrulayın</h1>

<p class="em-text">
    {user_name}, hesabınızı kullanmaya başlamak için aşağıdaki butona tıklayarak
    e-posta adresinizi doğrulayın.
</p>

<div class="em-btn-wrap">
    <a href="{verification_url}" class="em-btn">E-postamı Doğrula</a>
</div>

<hr class="em-divider">

<p class="em-text-sm">
    Bağlantının geçerlilik süresi 60 dakikadır. Bu hesabı siz oluşturmadıysanız
    bu e-postayı yok sayabilirsiniz.
</p>',
            'variables'   => json_encode([
                ['key' => 'user_name', 'label' => 'Kullanıcı Adı Soyadı', 'example' => 'Ahmet Yılmaz'],
                ['key' => 'site_name', 'label' => 'Site Adı', 'example' => 'Acme'],
                ['key' => 'verification_url', 'label' => 'Doğrulama Bağlantısı', 'example' => 'https://example.com/e-posta-dogrula/1/abc'],
            ], JSON_UNESCAPED_UNICODE),
            'is_active'   => true,
            'created_at'  => $now,
            'updated_at'  => $now,
            'deleted_at'  => null,
        ]);
    }

    public function down(): void
    {
        DB::table('mail_templates')->where('key', self::KEY)->delete();
    }
};
