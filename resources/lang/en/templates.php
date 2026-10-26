<?php

return [
    'label' => 'email template',
    'plural_label' => 'email templates',
    'fields' => [
        'name' => 'Name',
        'key' => 'Key',
        'body' => 'Body',
        'placeholders' => 'Placeholders',
        'css' => 'CSS',
    ],
    'columns' => [
        'name' => 'Name',
        'key' => 'Key',
        'updated_at' => 'Updated at',
    ],
    'helpers' => [
        'body' => 'Use [[ placeholder_name ]] tokens where draft content should be inserted. Declare every placeholder you use in the Placeholders field below.',
        'placeholders' => 'The list of [[ placeholder_name ]] tokens used in the body. The draft composer will render one input per placeholder, per locale.',
        'placeholders_example' => 'Add a placeholder name (e.g. greeting)',
    ],
];
