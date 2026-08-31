<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    |
    | This option controls the default cache store that will be used by the
    | framework. This connection is utilized if another isn't explicitly
    | specified when running a cache operation inside the application.
    |
    */

    'default' => env('CACHE_STORE', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the cache "stores" for your application as
    | well as their drivers. You may even define multiple stores for the
    | same cache driver to group types of items stored in your caches.
    |
    | Supported drivers: "array", "database", "file", "memcached",
    |                    "redis", "dynamodb", "octane",
    |                    "failover", "null"
    |
    */

    'stores' => [

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CACHE_CONNECTION'),
            'table' => env('DB_CACHE_TABLE', 'cache'),
            'lock_connection' => env('DB_CACHE_LOCK_CONNECTION'),
            'lock_table' => env('DB_CACHE_LOCK_TABLE'),
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],

        'memcached' => [
            'driver' => 'memcached',
            'persistent_id' => env('MEMCACHED_PERSISTENT_ID'),
            'sasl' => [
                env('MEMCACHED_USERNAME'),
                env('MEMCACHED_PASSWORD'),
            ],
            'options' => [
                // Memcached::OPT_CONNECT_TIMEOUT => 2000,
            ],
            'servers' => [
                [
                    'host' => env('MEMCACHED_HOST', '127.0.0.1'),
                    'port' => env('MEMCACHED_PORT', 11211),
                    'weight' => 100,
                ],
            ],
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_CACHE_CONNECTION', 'cache'),
            'lock_connection' => env('REDIS_CACHE_LOCK_CONNECTION', 'default'),
        ],

        'dynamodb' => [
            'driver' => 'dynamodb',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'table' => env('DYNAMODB_CACHE_TABLE', 'cache'),
            'endpoint' => env('DYNAMODB_ENDPOINT'),
        ],

        'octane' => [
            'driver' => 'octane',
        ],

        'failover' => [
            'driver' => 'failover',
            'stores' => [
                'database',
                'array',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | When utilizing the APC, database, memcached, Redis, and DynamoDB cache
    | stores, there might be other applications using the same cache. For
    | that reason, you may prefix every cache key to avoid collisions.
    |
    */

    'prefix' => env('CACHE_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')) . '-cache-'),

    /*
    |--------------------------------------------------------------------------
    | Önbellekten geri açılabilecek sınıflar
    |--------------------------------------------------------------------------
    |
    | Laravel 13 ile gelen sertleştirme: önbellekten okunan veri
    | unserialize edilirken yalnız burada listelenen sınıflar canlandırılıyor.
    | null bırakılırsa her sınıf serbest — önbellek deposunu ele geçiren biri
    | (paylaşımlı bir Redis, yazılabilir bir cache dizini) uygulamaya kendi
    | nesnesini enjekte edebilir.
    |
    | Liste dar tutuldu: yalnız bu kit'in gerçekten önbelleğe koyduğu şeyler.
    | Eloquent koleksiyonu saklayan yedi servis var (slider, sayfa, SSS,
    | popup, blog kategorisi, menü, dil) ve hepsi kendi modelini saklıyor;
    | biri listede olmasaydı ilgili ekran önbellek dolduktan sonra patlardı,
    | o yüzden testler yedi yolun hepsini de geziyor.
    |
    | Yeni bir modeli önbelleğe koyan herkes onu buraya da eklemek zorunda.
    | Bunun bir maliyeti var ama alternatifi, "her sınıf serbest" demenin
    | maliyetinden küçük.
    |
    */

    'serializable_classes' => [
        \Illuminate\Database\Eloquent\Collection::class,
        \Illuminate\Support\Collection::class,
        \Carbon\Carbon::class,
        \Illuminate\Support\Carbon::class,

        \App\Models\BlogCategory::class,
        \App\Models\BlogPost::class,
        \App\Models\Faq::class,
        \App\Models\GalleryCategory::class,
        \App\Models\GalleryItem::class,
        \App\Models\Language::class,
        \App\Models\Menu::class,
        \App\Models\MenuItem::class,
        \App\Models\Page::class,
        \App\Models\Popup::class,
        \App\Models\Setting::class,
        \App\Models\Slider::class,

        // Model nitelikleri enum taşıyor; koleksiyonla birlikte onlar da
        // geri açılıyor.
        \App\Enums\ContentStatus::class,
        \App\Enums\GalleryType::class,
        \App\Enums\PopupDisplayMode::class,
        \App\Enums\PopupPage::class,
        \App\Enums\PopupSize::class,
        \App\Enums\SettingGroup::class,
        \App\Enums\SettingType::class,
    ],

];
