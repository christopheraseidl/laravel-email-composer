<?php

// config for CSeidl/EmailComposer
return [
    // Locales the composer UI and emails support. Should be a subset of
    // (or equal to) the host app's available locales.
    'locales' => ['en', 'es'],
    'default_locale' => 'en',

    // Where rendered email HTML is stored.
    'storage' => [
        'disk' => env('EMAIL_COMPOSER_DISK', 'local'),
        'path' => 'email-composer',
        // Disk for files email clients load by URL, such as an uploaded logo.
        'public_disk' => env('EMAIL_COMPOSER_PUBLIC_DISK', 'public'),
    ],

    // Where email templates live in the consuming app. Files dropped here
    // are auto-discovered by the TemplateRegistry.
    'templates' => [
        'path' => resource_path('views/vendor/email-composer/templates'),
    ],

    // Sending behaviour.
    'queue' => [
        'connection' => env('EMAIL_COMPOSER_QUEUE_CONNECTION', null),
        'queue' => env('EMAIL_COMPOSER_QUEUE_NAME', null),
    ],

    // Send jobs added to the batch per queue push when a draft is sent.
    // Bounds memory and the size of each push (one insert on the database driver).
    'bulk' => [
        'chunk_size' => 100,
    ],

    // Unsubscribe URL is signed and expires after this many days.
    // null = no expiry.
    'unsubscribe' => [
        'link_ttl_days' => null,
    ],

    // Signed review links for external reviewers expire after this many days.
    'review' => [
        'link_ttl_days' => 7,
    ],

    // Navigation group the Filament resources and theme page appear under.
    // null = no group.
    'filament' => [
        'navigation_group' => 'Email',
    ],
];
