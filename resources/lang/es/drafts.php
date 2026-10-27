<?php

return [
    'actions' => [
        'preview' => 'Vista previa',
        'send_test' => 'Enviar prueba',
        'review_link' => 'Enlace de revisión',
        'submit' => 'Enviar a revisión',
        'approve' => 'Aprobar',
        'return_to_draft' => 'Devolver a borrador',
        'send' => 'Enviar',
        'close' => 'Cerrar',
    ],
    'fields' => [
        'locale' => 'Idioma',
        'review_link' => 'Enlace de revisión',
        'send_to' => 'Enviar a',
    ],
    'placeholders' => [
        'send_to' => 'Añade una dirección de correo',
    ],
    'tooltips' => [
        'lacks_audience' => 'Añade destinatarios antes de enviar.',
    ],
    'notifications' => [
        'not_approved' => 'Borrador no aprobado',
        'not_returned' => 'Borrador no devuelto',
        'not_sent' => 'Borrador no enviado',
        'test_not_sent' => 'Correo de prueba no enviado',
        'test_sent' => 'Correo de prueba enviado',
        'test_sent_body' => 'Enviado a :emails.',
    ],
];
