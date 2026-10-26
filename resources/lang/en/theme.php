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
    ],
    'notifications' => [
        'saved' => 'Saved',
    ],
    'helpers' => [
        'logo_url' => 'An absolute URL. Email clients cannot load relative paths. Clear it to upload a file instead.',
        'logo_path' => 'Remove the upload to enter a URL instead.',
    ],
];
