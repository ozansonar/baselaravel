<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Password reset texts
|--------------------------------------------------------------------------
|
| Laravel's password broker returns these keys. Without the file the Turkish
| fallback answered, so /en showed Turkish reset messages.
|
*/

return [
    'reset'     => 'Your password has been reset.',
    'sent'      => 'A password reset link has been sent to your e-mail address.',
    'throttled' => 'Please wait before retrying.',
    'token'     => 'This password reset link is invalid.',
    'user'      => 'No account is registered with this e-mail address.',
];
