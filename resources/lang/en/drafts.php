<?php

return [
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
];
