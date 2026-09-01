<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Authentication texts
|--------------------------------------------------------------------------
|
| The Turkish file exists, and the fallback locale is Turkish too, so an
| English visitor was reading Turkish sign-in errors: the fallback filled the
| gap silently instead of leaving anything visibly missing.
|
*/

return [
    'failed'   => 'These credentials do not match any account.',
    'password' => 'The password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',
];
