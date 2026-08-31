<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Zamanlanmış rapor e-postasının şablonu.
 *
 * Metin panelden düzenlenebilir olmalı: raporu alan kişi çoğu zaman
 * geliştirici değil, ve "bu raporu neden alıyorum" sorusunun cevabı zamanla
 * değişebilir. Şablon silinirse BaseMail kendi Blade görünümüne düşüyor.
 */
return new class extends Migration
{
    private const KEY = 'scheduled_report';

    public function up(): void
    {
        if (DB::table('mail_templates')->where('key', self::KEY)->exists()) {
            return;
        }

        DB::table('mail_templates')->insert([
            'key'         => self::KEY,
            'name'        => 'Zamanlanmış Rapor',
            'description' => 'Panelde tanımlanan zamanlanmış raporun alıcılara gönderildiği e-posta.',
            'subject'     => '{report_title} - {site_name}',
            'body'        => '<p class="em-greeting">Rapor</p>
<h1 class="em-heading">{report_title}</h1>

<p class="em-text">
    {frequency} çalışan raporunuz bu e-postanın ekinde. Dosya seçtiğiniz
    biçimde (Excel ya da PDF) hazırlandı.
</p>

<table class="em-info-box" role="presentation" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td class="em-info-box-td">
            <p class="em-info-row"><span class="em-info-label">Tarih aralığı:</span> {report_range}</p>
            <p class="em-info-row"><span class="em-info-label">Sıklık:</span> {frequency}</p>
        </td>
    </tr>
</table>

<p class="em-text-sm">
    Bu raporu almayı bırakmak için panelden Raporlar &rarr; Zamanlanan Raporlar
    bölümündeki tanımı kapatabilirsiniz.
</p>',
            'variables' => json_encode([
                ['key' => 'report_title', 'label' => 'Rapor Adı', 'example' => 'Trafik Raporu (01.08.2026 – 31.08.2026)'],
                ['key' => 'report_range', 'label' => 'Tarih Aralığı', 'example' => '01.08.2026 – 31.08.2026'],
                ['key' => 'frequency', 'label' => 'Sıklık', 'example' => 'Her pazartesi'],
                ['key' => 'site_name', 'label' => 'Site Adı', 'example' => 'Laravel Base'],
            ], JSON_UNESCAPED_UNICODE),
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);
    }

    public function down(): void
    {
        DB::table('mail_templates')->where('key', self::KEY)->delete();
    }
};
