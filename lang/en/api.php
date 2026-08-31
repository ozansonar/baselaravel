<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| API messages
|--------------------------------------------------------------------------
| Only the API-specific strings live here. Anything the front-end also says
| (login failed, account deactivated, message received) is read from the
| `site` group, so the same sentence is not maintained in two files.
*/

return [

    'common' => [
        'ok'                 => 'Request completed.',
        'error'              => 'The request could not be processed.',
        'not_found'          => 'Not found.',
        'forbidden'          => 'You are not allowed to perform this action.',
        'method_not_allowed' => 'That method is not supported for this endpoint.',
        'validation_failed'  => 'The given data was invalid.',
        'too_many_requests'  => 'Too many requests. Please wait a moment and try again.',
        'server_error'       => 'Something went wrong. Please try again later.',
        'unavailable'        => 'The service is currently unavailable.',
    ],

    'auth' => [
        'registered'            => 'Your account has been created.',
        'logged_in'             => 'Signed in.',
        'logged_out'            => 'Signed out.',
        'unauthenticated'       => 'You must be signed in to do that.',
        'registration_disabled' => 'New registrations are currently closed.',
        'verification_sent'     => 'A verification link has been sent to your e-mail address.',
        'already_verified'      => 'Your e-mail address is already verified.',
        'email_unverified'      => 'Verify your e-mail address before doing that.',
    ],

    'password' => [
        'code_sent'     => 'If the address is registered, a reset code has been sent.',
        'code_invalid'  => 'That code is invalid or has expired. Request a new one.',
        'code_required' => 'The reset code is required.',
        'code_digits'   => 'The reset code must be 6 digits.',
        'reset'         => 'Your password has been changed. You can sign in with it now.',
    ],

    'pages' => [
        'not_found' => 'Page not found.',
    ],

    'devices' => [
        'not_found'      => 'No such session.',
        'revoked'        => 'The session has been signed out.',
        'others_revoked' => ':count session(s) on other devices were signed out.',
    ],

    'menus' => [
        'not_found' => 'No published menu for that location.',
    ],

    'settings' => [
        'group_not_found' => 'No such settings group.',
    ],

    'translations' => [
        'group_not_found' => 'No such translation group.',
    ],

    'blog' => [
        'category_not_found' => 'No such category.',
        'post_not_found'     => 'Post not found.',
    ],

    'gallery' => [
        'category_not_found' => 'No such category.',
        'invalid_type'       => 'Invalid type. Allowed values: photo, video.',
    ],

];
