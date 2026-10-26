<?php

return [
    'label' => 'recipient',
    'plural_label' => 'recipients',
    'fields' => [
        'name' => 'Name',
        'email' => 'Email address',
        'locale' => 'Locale',
        'lists' => 'Lists',
        'metadata' => 'Metadata',
        'unsubscribed_at' => 'Unsubscribed at',
    ],
    'columns' => [
        'name' => 'Name',
        'email' => 'Email address',
        'locale' => 'Locale',
        'unsubscribed_at' => 'Unsubscribed at',
        'created_at' => 'Created at',
        'updated_at' => 'Updated at',
    ],
    'filters' => [
        'subscription' => [
            'label' => 'Subscription',
            'all' => 'All',
            'unsubscribed' => 'Unsubscribed',
            'subscribed' => 'Subscribed',
        ],
        'lists' => 'Lists',
    ],
];
