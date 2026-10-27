<?php

return [
    'label' => 'email draft',
    'plural_label' => 'email drafts',
    'actions' => [
        'preview' => 'Preview',
        'send_test' => 'Send test',
        'review_link' => 'Review link',
        'submit' => 'Submit',
        'approve' => 'Approve',
        'return_to_draft' => 'Return to draft',
        'send' => 'Send',
        'close' => 'Close',
    ],
    'fields' => [
        'locale' => 'Locale',
        'review_link' => 'Review link',
        'send_to' => 'Send to',
        'status' => 'Status',
        'template' => 'Template',
        'template_groups' => [
            'database' => 'Database',
            'files' => 'Files',
        ],
        'subject' => 'Subject',
        'placeholders' => 'Placeholders',
        'audience' => 'Audience',
        'recipient_list' => 'Recipient list',
        'recipients' => 'Recipients',
    ],
    'columns' => [
        'subject' => 'Subject',
        'author' => 'Author',
        'no_author' => 'N/A',
        'author_fallback' => 'Author #:id',
        'created_at' => 'Created at',
        'updated_at' => 'Updated at',
    ],
    'filters' => [
        'status' => 'Status',
    ],
    'helpers' => [
        'pick_template' => 'Pick a template to see its placeholders.',
        'preview' => 'Shows the last saved version. Save first to preview unsaved changes.',
    ],
    'placeholders' => [
        'send_to' => 'Add an email address',
    ],
    'tooltips' => [
        'lacks_audience' => 'Add recipients before sending.',
    ],
    'notifications' => [
        'not_approved' => 'Draft not approved',
        'not_returned' => 'Draft not returned',
        'not_sent' => 'Draft not sent',
        'test_not_sent' => 'Test email not sent',
        'test_sent' => 'Test email sent',
        'test_sent_body' => 'Sent to :emails.',
    ],
    'status' => [
        'draft' => 'Draft',
        'under_review' => 'Under Review',
        'approved' => 'Approved',
        'sending' => 'Sending',
        'sent' => 'Sent',
    ],
    'audience' => [
        'all' => 'All recipients',
        'list' => 'A recipient list',
        'selected' => 'Selected recipients',
    ],
];
