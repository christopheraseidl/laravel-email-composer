<?php

return [
    'title' => 'Manage Email Theme',
    'navigation_label' => 'Manage Email Theme',
    'fields' => [
        'logo_url' => 'Logo URL',
        'logo_path' => 'Logo upload',
        'logo_alt' => 'Logo alt text',
        'css' => 'CSS',
    ],
    'actions' => [
        'save' => 'Save',
        'restore_default' => 'Restore default CSS',
    ],
    'notifications' => [
        'saved' => 'Saved',
        'default_restored' => 'Default CSS restored',
    ],
    'restore_default' => [
        'heading' => 'Restore the default CSS?',
        'description' => 'This replaces the theme CSS with the package default. The logo is kept.',
    ],
    'helpers' => [
        'logo_url' => 'An absolute URL. Email clients cannot load relative paths. Clear it to upload a file instead.',
        'logo_path' => 'Remove the upload to enter a URL instead.',
    ],
];
