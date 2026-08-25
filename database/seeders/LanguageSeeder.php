<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        $languages = [
            ['code' => 'tr', 'name' => 'Türkçe',    'native_name' => 'Türkçe',   'flag' => '🇹🇷', 'is_default' => true,  'is_active' => true,  'sort_order' => 0],
            ['code' => 'en', 'name' => 'İngilizce', 'native_name' => 'English',  'flag' => '🇬🇧', 'is_default' => false, 'is_active' => true,  'sort_order' => 1],
            ['code' => 'de', 'name' => 'Almanca',   'native_name' => 'Deutsch',  'flag' => '🇩🇪', 'is_default' => false, 'is_active' => false, 'sort_order' => 2],
            ['code' => 'fr', 'name' => 'Fransızca', 'native_name' => 'Français', 'flag' => '🇫🇷', 'is_default' => false, 'is_active' => false, 'sort_order' => 3],
            ['code' => 'it', 'name' => 'İtalyanca', 'native_name' => 'Italiano', 'flag' => '🇮🇹', 'is_default' => false, 'is_active' => false, 'sort_order' => 4],
        ];

        foreach ($languages as $language) {
            Language::updateOrCreate(['code' => $language['code']], $language);
        }
    }
}
