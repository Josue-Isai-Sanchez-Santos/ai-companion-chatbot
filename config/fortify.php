<?php

use Laravel\Fortify\Features;

return [
    /*
    |--------------------------------------------------------------------------
    | Authentication guard
    |--------------------------------------------------------------------------
    */

    'guard' => 'web',

    /*
    |--------------------------------------------------------------------------
    | Authentication middleware
    |--------------------------------------------------------------------------
    */

    'middleware' => ['web'],

    'auth_middleware' => 'auth',

    /*
    |--------------------------------------------------------------------------
    | Authentication fields
    |--------------------------------------------------------------------------
    */

    'passwords' => 'users',

    'username' => 'email',

    'email' => 'email',

    'lowercase_usernames' => true,

    /*
    |--------------------------------------------------------------------------
    | Views and redirects
    |--------------------------------------------------------------------------
    */

    'views' => true,

    'home' => '/chat',

    'prefix' => '',

    'domain' => null,

    /*
    |--------------------------------------------------------------------------
    | Rate limiters
    |--------------------------------------------------------------------------
    */

    'limiters' => [
        'login' => 'login',
        'passkeys' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Route paths
    |--------------------------------------------------------------------------
    */

    'paths' => [
        'login' => null,
        'logout' => null,

        'password' => [
            'request' => null,
            'reset' => null,
            'email' => null,
            'update' => null,
            'confirm' => null,
            'confirmation' => null,
        ],

        'register' => null,

        'verification' => [
            'notice' => null,
            'verify' => null,
            'send' => null,
        ],

        'user-profile-information' => [
            'update' => null,
        ],

        'user-password' => [
            'update' => null,
        ],

        'two-factor' => [
            'login' => null,
            'enable' => null,
            'confirm' => null,
            'disable' => null,
            'qr-code' => null,
            'secret-key' => null,
            'recovery-codes' => null,
        ],

        'passkey' => [
            'login-options' => null,
            'login' => null,
            'confirm-options' => null,
            'confirm' => null,
            'registration-options' => null,
            'store' => null,
            'destroy' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Redirect overrides
    |--------------------------------------------------------------------------
    */

    'redirects' => [
        'login' => '/chat',
        'logout' => '/',
        'password-confirmation' => null,
        'register' => '/chat',
        'email-verification' => null,
        'password-reset' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Enabled features
    |--------------------------------------------------------------------------
    */

    'features' => [
        Features::registration(),
    ],
];
