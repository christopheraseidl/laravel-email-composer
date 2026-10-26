<?php

return [
    'fields' => [
        'file' => 'CSV file',
        'list' => 'Add to list',
    ],
    'actions' => [
        'import' => 'Import',
    ],
    'notifications' => [
        'queued' => [
            'title' => 'Import queued',
            'body' => 'You will get a notification when it finishes.',
        ],
        'imported' => 'Recipients imported',
        'partly_failed' => 'Import partly failed',
        'failed' => 'Import failed',
        'totals' => 'Created :created, updated :updated, skipped :skipped, rejected :rejected.',
        'parts_failed' => ':failed of :parts parts failed. Some of their rows may be imported.',
        'rejected_line' => 'Line :line: :reason',
        'missing_column' => 'The file has no :column column.',
        'missing_list' => 'The recipient list no longer exists.',
        'stopped' => 'The import stopped with an error before any rows were imported.',
    ],
    'helpers' => [
        'file' => 'A header row with an email column, and optionally name, locale and any metadata columns. Up to :size KB.',
    ],
];
