<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Upload Directory
    |--------------------------------------------------------------------------
    |
    | Absolute path uploads are written to. Defaults to public/uploads so files
    | are served directly by the web server without a storage symlink.
    |
    | The test suite points this at a throwaway directory so running tests never
    | leaves files behind in the real upload folder.
    |
    */

    'path' => env('UPLOADS_PATH') ?: public_path('uploads'),

];
