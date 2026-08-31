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

];
