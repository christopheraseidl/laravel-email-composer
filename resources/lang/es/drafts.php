<?php

return [
    'label' => 'borrador de correo',
    'plural_label' => 'borradores de correo',
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
        'status' => 'Estado',
        'template' => 'Plantilla',
        'template_groups' => [
            'database' => 'Base de datos',
            'files' => 'Archivos',
        ],
        'subject' => 'Asunto',
        'placeholders' => 'Marcadores',
        'audience' => 'Audiencia',
        'recipient_list' => 'Lista de destinatarios',
        'recipients' => 'Destinatarios',
    ],
    'columns' => [
        'subject' => 'Asunto',
        'author' => 'Autor',
        'no_author' => 'N/D',
        'author_fallback' => 'Autor n.º :id',
        'created_at' => 'Fecha de creación',
        'updated_at' => 'Fecha de actualización',
    ],
    'filters' => [
        'status' => 'Estado',
    ],
    'helpers' => [
        'pick_template' => 'Seleccione una plantilla para ver sus marcadores.',
        'preview' => 'Muestra la última versión guardada. Guarde primero para previsualizar los cambios sin guardar.',
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
    'status' => [
        'draft' => 'Borrador',
        'under_review' => 'En revisión',
        'approved' => 'Aprobado',
        'sending' => 'Enviando',
        'sent' => 'Enviado',
    ],
    'audience' => [
        'all' => 'Todos los destinatarios',
        'list' => 'Una lista de destinatarios',
        'selected' => 'Destinatarios seleccionados',
    ],
];
