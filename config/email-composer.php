<?php

// config for CSeidl/EmailComposer
return [
    // Locales the composer UI and emails support. Should be a subset of
    // (or equal to) the host app's available locales.
    'locales' => ['en', 'es', 'fr'],
    'default_locale' => 'en',

    // Where rendered email HTML is stored.
    'storage' => [
        'disk' => env('EMAIL_COMPOSER_DISK', 'local'),
        'path' => 'email-composer',
    ],

    // Where email templates live in the consuming app. Files dropped here
    // are auto-discovered by the TemplateRegistry.
    'templates' => [
        'path' => public_path('vendor/email-composer/templates'),
    ],

    // Global logo. Per-template logos (set via Filament) override this.
    'logo' => [
        'path' => null,       // e.g. public_path('logo.png') or a URL
        'alt' => env('APP_NAME', 'Company'),
    ],

    // Sending behaviour.
    'queue' => [
        'enabled' => env('EMAIL_COMPOSER_QUEUE', true),
        'connection' => env('EMAIL_COMPOSER_QUEUE_CONNECTION', null),
        'queue' => env('EMAIL_COMPOSER_QUEUE_NAME', 'emails'),
    ],

    // Bulk-send chunking: dispatch N jobs at a time, sleep between batches.
    'bulk' => [
        'chunk_size' => 100,
    ],

    // Unsubscribe URL is signed and expires after this many days.
    // null = no expiry.
    'unsubscribe' => [
        'link_ttl_days' => null,
    ],

    // Filament panel(s) the EmailComposerPlugin should attach to.
    // null = attach when the plugin is registered on a panel directly.
    'filament' => [
        'navigation_group' => 'Email',
    ],
];
