<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Seeded Account Password
    |--------------------------------------------------------------------------
    |
    | Password given to the demo accounts UserSeeder creates. Set SEED_PASSWORD
    | in .env so each deployment gets its own and the real one never lands in
    | version control — every project cloned from this base kit would otherwise
    | ship with the same known credentials.
    |
    | The fallback covers a fresh clone with nothing configured. It is a demo
    | password, not a safe one: change the admin password from the panel before
    | the site goes public.
    |
    */

    'password' => env('SEED_PASSWORD') ?: 'Demo*12345.',

];
