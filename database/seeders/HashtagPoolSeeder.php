<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\HashtagPool;
use Illuminate\Database\Seeder;

/**
 * Orhan Babanın Çiftliği için başlangıç hashtag havuzu.
 * Genel/lokasyon/ürün kategorilerine göre 30+ hashtag ile başlar.
 * Admin sonra manuel ekleyebilir/silebilir.
 */
final class HashtagPoolSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            // Genel — markanın temel kelimeleri
            ['genel', 'orhanbabaninciftligi'],
            ['genel', 'doğal'],
            ['genel', 'organik'],
            ['genel', 'natural'],
            ['genel', 'sağlıklıbeslenme'],
            ['genel', 'sağlıklıyaşam'],
            ['genel', 'katkısız'],
            ['genel', 'glütensiz'],

            // Ürün kategorisi
            ['urun', 'köyürünleri'],
            ['urun', 'köysütü'],
            ['urun', 'köytereyağı'],
            ['urun', 'köypeyniri'],
            ['urun', 'köyyumurtası'],
            ['urun', 'çiğsüt'],
            ['urun', 'kaymak'],
            ['urun', 'tereyağı'],
            ['urun', 'beyazpeynir'],
            ['urun', 'lor'],
            ['urun', 'çökelek'],
            ['urun', 'yoğurt'],

            // Lokasyon
            ['lokasyon', 'çorum'],
            ['lokasyon', 'çorumdan'],
            ['lokasyon', 'köyden'],
            ['lokasyon', 'anadolu'],
            ['lokasyon', 'türkiye'],
            ['lokasyon', 'türkmalı'],

            // Trend (manuel olarak güncellenir)
            ['trend', 'kahvaltı'],
            ['trend', 'kahvaltıkeyfi'],
            ['trend', 'lezzet'],
            ['trend', 'gurme'],
            ['trend', 'evyapımı'],
            ['trend', 'çiftlik'],
            ['trend', 'çiftlikten'],

            // Sezon (admin değiştirir)
            ['sezon', 'bahar'],
            ['sezon', 'taze'],
        ];

        $sortOrder = 10;
        foreach ($tags as [$category, $tag]) {
            HashtagPool::updateOrCreate(
                ['tag' => HashtagPool::normalize($tag)],
                [
                    'category'   => $category,
                    'is_active'  => true,
                    'sort_order' => $sortOrder,
                ],
            );
            $sortOrder += 10;
        }
    }
}
