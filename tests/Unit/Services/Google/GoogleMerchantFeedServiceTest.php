<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Google;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Services\Google\GoogleMerchantFeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class GoogleMerchantFeedServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_eligible_products_only_includes_active_products(): void
    {
        Cache::flush();
        $category = Category::create(['name' => 'Süt Ürünleri', 'slug' => 'sut-urunleri', 'is_active' => true]);

        Product::create([
            'category_id'       => $category->id,
            'name'              => 'Aktif Ürün',
            'slug'              => 'aktif-urun',
            'description'       => 'Çok güzel bir aktif ürün açıklaması burada bulunuyor.',
            'price'             => 100.00,
            'stock'             => 10,
            'status'            => ProductStatus::Active,
            'image'             => 'test.jpg',
        ]);

        Product::create([
            'category_id' => $category->id,
            'name'        => 'Pasif Ürün',
            'slug'        => 'pasif-urun',
            'price'       => 50.00,
            'stock'       => 5,
            'status'      => ProductStatus::Inactive,
        ]);

        $service = app(GoogleMerchantFeedService::class);
        $products = $service->eligibleProducts();

        $this->assertSame(1, $products->count());
        $this->assertSame('aktif-urun', $products->first()->slug);
    }

    public function test_xml_contains_required_google_shopping_fields(): void
    {
        Cache::flush();
        $category = Category::create(['name' => 'Tereyağı', 'slug' => 'tereyagi', 'is_active' => true]);

        Product::create([
            'category_id'       => $category->id,
            'name'              => 'Köy Tereyağı 1kg',
            'slug'              => 'koy-tereyagi-1kg',
            'description'       => 'Çorum köylerinden taze, doğal köy tereyağı. 1 kg ambalajda gelir.',
            'price'             => 250.00,
            'stock'             => 5,
            'status'            => ProductStatus::Active,
            'image'             => 'tereyagi.jpg',
        ]);

        $service = app(GoogleMerchantFeedService::class);
        $xml = $service->buildXml();

        // RSS yapısı + namespace
        $this->assertStringContainsString('<rss version="2.0"', $xml);
        $this->assertStringContainsString('xmlns:g="http://base.google.com/ns/1.0"', $xml);

        // Zorunlu alanlar
        $this->assertStringContainsString('<g:id>', $xml);
        $this->assertStringContainsString('<g:title>Köy Tereyağı 1kg</g:title>', $xml);
        $this->assertStringContainsString('<g:description>', $xml);
        $this->assertStringContainsString('<g:link>', $xml);
        $this->assertStringContainsString('<g:image_link>', $xml);
        $this->assertStringContainsString('<g:availability>in_stock</g:availability>', $xml);
        $this->assertStringContainsString('<g:price>250.00 TRY</g:price>', $xml);
        $this->assertStringContainsString('<g:condition>new</g:condition>', $xml);
        $this->assertStringContainsString('<g:brand>', $xml);
        $this->assertStringContainsString('<g:identifier_exists>no</g:identifier_exists>', $xml);
    }

    public function test_xml_uses_sale_price_when_discount_exists(): void
    {
        Cache::flush();
        $category = Category::create(['name' => 'Peynir', 'slug' => 'peynir', 'is_active' => true]);

        Product::create([
            'category_id'      => $category->id,
            'name'             => 'Köy Peyniri',
            'slug'             => 'koy-peyniri',
            'description'      => 'Çiğ sütten yapılan geleneksel köy peyniri tarif edilen şekilde.',
            'price'            => 150.00,    // satış fiyatı
            'discounted_price' => 200.00,    // üstü çizili orijinal
            'stock'            => 10,
            'status'           => ProductStatus::Active,
            'image'            => 'peynir.jpg',
        ]);

        $service = app(GoogleMerchantFeedService::class);
        $xml = $service->buildXml();

        $this->assertStringContainsString('<g:price>200.00 TRY</g:price>', $xml);      // orijinal
        $this->assertStringContainsString('<g:sale_price>150.00 TRY</g:sale_price>', $xml); // indirimli
    }

    public function test_out_of_stock_products_marked_correctly(): void
    {
        Cache::flush();
        $category = Category::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);

        Product::create([
            'category_id' => $category->id,
            'name'        => 'Stoksuz Ürün',
            'slug'        => 'stoksuz',
            'description' => 'Bu ürünün stoğu kalmadı, ama feed\'de görünmesi gerekiyor.',
            'price'       => 100.00,
            'stock'       => 0,
            'status'      => ProductStatus::Active,
            'image'       => 'test.jpg',
        ]);

        $service = app(GoogleMerchantFeedService::class);
        $xml = $service->buildXml();

        $this->assertStringContainsString('<g:availability>out_of_stock</g:availability>', $xml);
    }

    public function test_validate_product_detects_missing_fields(): void
    {
        $category = Category::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);

        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Ek',                                    // çok kısa
            'slug'        => 'ek',
            'description' => 'kısa',                                  // çok kısa
            'price'       => 0,                                       // 0
            'stock'       => 1,
            'status'      => ProductStatus::Active,
            // image yok
        ]);

        $service = app(GoogleMerchantFeedService::class);
        $errors  = $service->validateProduct($product);

        $this->assertContains('Başlık çok kısa (min 3 karakter)', $errors);
        $this->assertContains('Açıklama çok kısa (min 30 karakter)', $errors);
        $this->assertContains('Fiyat eksik veya 0', $errors);
        $this->assertContains('Görsel eksik', $errors);
    }

    public function test_validate_product_passes_for_valid_product(): void
    {
        $category = Category::create(['name' => 'Süt', 'slug' => 'sut', 'is_active' => true]);

        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Tam Ürün',
            'slug'        => 'tam-urun',
            'description' => 'Yeterince uzun bir açıklama burada bulunmaktadır geçerli olduğu kanıtlamak için.',
            'price'       => 99.99,
            'stock'       => 10,
            'status'      => ProductStatus::Active,
            'image'       => 'urun.jpg',
        ]);

        $service = app(GoogleMerchantFeedService::class);
        $errors  = $service->validateProduct($product);

        $this->assertSame([], $errors);
    }

    public function test_html_in_description_is_stripped(): void
    {
        Cache::flush();
        $category = Category::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);

        Product::create([
            'category_id' => $category->id,
            'name'        => 'HTML Ürün',
            'slug'        => 'html-urun',
            'description' => '<p>Bu <strong>kalın</strong> bir <em>açıklama</em> içerir HTML tag\'leri olan.</p>',
            'price'       => 100.00,
            'stock'       => 5,
            'status'      => ProductStatus::Active,
            'image'       => 'test.jpg',
        ]);

        $service = app(GoogleMerchantFeedService::class);
        $xml = $service->buildXml();

        $this->assertStringNotContainsString('<strong>', $xml);
        $this->assertStringNotContainsString('<em>', $xml);
        $this->assertStringContainsString('Bu kalın bir açıklama', $xml);
    }
}
