<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Backup Directory
    |--------------------------------------------------------------------------
    |
    | Where backup archives are written. Deliberately under storage/ and not
    | public/: an archive here carries the whole database, and anything served
    | by the web server can be fetched by whoever guesses its name.
    |
    | The test suite points this at a throwaway directory (see phpunit.xml), so
    | running tests never writes into — or rotates away — a developer's real
    | backups.
    |
    */

    'path' => env('BACKUPS_PATH') ?: storage_path('app/backups'),

    /*
    |--------------------------------------------------------------------------
    | Dış kopya
    |--------------------------------------------------------------------------
    |
    | Yedeğin ikinci kopyası. Buraya kadar yedekleme tek bir varsayıma
    | dayanıyordu: disk sağlam. Oysa yedeğin var olma sebebi tam da o
    | varsayımın çökmesi — diski kaybeden tek kopyayı da kaybediyordu.
    |
    | driver:
    |   none  → kapalı (varsayılan; kimseye bir şey dayatmıyor)
    |   local → başka bir yola kopyala (bağlanan ağ klasörü, ikinci disk)
    |   ftp   → başka bir sunucuya yükle (PHP'nin kendi ftp eklentisiyle)
    |
    | S3 gibi bir hedef ayrı bir kütüphane ister; bu kit hiçbir bulut
    | sağlayıcısına bağlanmıyor.
    |
    */

    'offsite' => [
        'driver' => env('BACKUP_OFFSITE_DRIVER', 'none'),

        'path' => env('BACKUP_OFFSITE_PATH'),

        'ftp' => [
            'host'     => env('BACKUP_OFFSITE_FTP_HOST'),
            'port'     => (int) env('BACKUP_OFFSITE_FTP_PORT', 21),
            'username' => env('BACKUP_OFFSITE_FTP_USERNAME'),
            'password' => env('BACKUP_OFFSITE_FTP_PASSWORD'),
            'path'     => env('BACKUP_OFFSITE_FTP_PATH', 'backups'),
            'passive'  => (bool) env('BACKUP_OFFSITE_FTP_PASSIVE', true),
        ],

        // Dış kopyanın saklama süresi. Olmasaydı hedef zamanla dolar ve
        // dolduğu gün yeni kopya alınamazdı — hem de kimse fark etmeden.
        'retention_days' => (int) env('BACKUP_OFFSITE_RETENTION_DAYS', 30),
    ],

];
