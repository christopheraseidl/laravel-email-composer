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

    // Largest CSV the panel's recipient import accepts, in kilobytes. PHP's
    // upload_max_filesize and post_max_size, and Livewire's temporary upload
    // rules (12 MB by default), must allow it too.
    'import' => [
        'max_file_size' => 10240,
        // Rows per queued job. Keep each job well inside the worker's timeout.
        'rows_per_part' => 2000,
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

    // Navigation group the Filament resources and theme page appear under:
    // a translation key or a plain string. null = no group.
    'filament' => [
        'navigation_group' => 'email-composer::navigation.group',
    ],
];
